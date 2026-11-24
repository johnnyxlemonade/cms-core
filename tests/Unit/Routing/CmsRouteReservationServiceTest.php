<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\CmsReservedPathGuard;
use Lemonade\Cms\Routing\Cms\CmsRouteCandidateResolver;
use Lemonade\Cms\Routing\Cms\CmsRouteRepository;
use Lemonade\Cms\Routing\Cms\CmsRouteReservationService;
use Lemonade\Cms\Routing\Cms\CmsRouteValidationException;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Verifies bounded canonical reservation retries without a database
 */
final class CmsRouteReservationServiceTest extends TestCase
{
    /**
     * Recognizes an existing target without reserving a new path
     */
    public function testRecognizesExistingTargetReservation(): void
    {
        $connection = new ReservationConnection(0, 'stable-path');
        $service = $this->service($connection);

        self::assertTrue($service->hasReservation('cms.news', 7, 'cs'));
        self::assertSame('stable-path', $service->reservationPath('cms.news', 7, 'cs'));
        self::assertSame([], $connection->attemptedPaths);
    }

    /**
     * Reloads routes and chooses the next suffix after a concurrent conflict
     */
    public function testRetriesUniqueConflictWithNextCandidate(): void
    {
        $connection = new ReservationConnection(1);
        $service = $this->service($connection);

        $reservation = $service->reserveAutomatic('cms.news', 7, 'cs', 'test');

        self::assertSame('test-1', $reservation->getSlug());
        self::assertSame(['test', 'test-1'], $connection->attemptedPaths);
    }

    /**
     * Stops after a fixed number of repeated conflicts
     */
    public function testStopsAfterMaximumReservationAttempts(): void
    {
        $connection = new ReservationConnection(10);
        $service = $this->service($connection);

        try {
            $service->reserveAutomatic('cms.news', 7, 'cs', 'test');
            self::fail('Expected reservation conflict.');
        } catch (DatabaseException) {
            self::assertSame(['test', 'test-1', 'test-2', 'test-3'], $connection->attemptedPaths);
        }
    }

    /**
     * Automatic reservation avoids a reserved system segment with a suffix
     */
    public function testAutomaticReservationSkipsReservedSystemPath(): void
    {
        $connection = new ReservationConnection(0);
        $service = $this->service($connection);

        $reservation = $service->reserveAutomatic('cms.news', 7, 'cs', 'api');

        self::assertSame('api-1', $reservation->getPath());
    }

    /**
     * Explicit reservation rejects a reserved path
     */
    public function testExplicitReservationRejectsReservedPath(): void
    {
        $service = $this->service(new ReservationConnection(0));

        $this->expectException(CmsRouteValidationException::class);
        $this->expectExceptionMessage('cms.route.path_reserved');

        $service->reserveExplicit('cms.page', 7, 'cs', '/assets/images');
    }

    /**
     * Explicit conflicts are not suffixed and cause no write
     */
    public function testExplicitReservationRejectsOccupiedPathWithoutSuffix(): void
    {
        $connection = new ReservationConnection(0, occupiedPaths: ['kontakt']);
        $service = $this->service($connection);

        try {
            $service->reserveExplicit('cms.page', 7, 'cs', 'kontakt');
            self::fail('Expected occupied explicit route.');
        } catch (CmsRouteValidationException $exception) {
            self::assertSame('cms.route.path_occupied', $exception->getMessage());
            self::assertSame([], $connection->attemptedPaths);
        }
    }

    /**
     * Moves all subtree paths in one transaction
     */
    public function testReplacesSubtreePathsInSingleTransaction(): void
    {
        $connection = new BatchReservationConnection([
            ['id' => 1, 'module_code' => 'cms.page', 'entity_id' => 10, 'locale' => 'cs', 'path' => 'obec'],
            ['id' => 2, 'module_code' => 'cms.page', 'entity_id' => 11, 'locale' => 'cs', 'path' => 'obec/kontakt'],
        ]);

        $this->service($connection)->replaceExplicitBatch('cms.page', [
            ['entityId' => 10, 'locale' => 'cs', 'path' => 'region/obec'],
            ['entityId' => 11, 'locale' => 'cs', 'path' => 'region/obec/kontakt'],
        ]);

        self::assertSame(1, $connection->transactions);
        self::assertSame(['region/obec', 'region/obec/kontakt'], array_column($connection->rows, 'path'));
    }

    private function service(ConnectionInterface $connection): CmsRouteReservationService
    {
        $database = new Database($connection, self::createStub(DatabaseDriverInterface::class));

        $router = new Router();
        $router->getNamed(
            'frontend.cms-route',
            '/{path:any}',
            ControllerAction::for(self::class, 'fallback'),
        );

        return new CmsRouteReservationService(
            new CmsRouteRepository($database),
            new CmsRouteCandidateResolver(),
            new CmsReservedPathGuard($router, new ServerRequest('GET', '/admin'), new ReservationLocaleRegistry()),
        );
    }

    public function fallback(): void {}
}

/**
 * Provides a locale snapshot for route reservation tests
 */
final readonly class ReservationLocaleRegistry implements PublicLocaleRegistryInterface
{
    /**
     * Returns the enabled default locale
     */
    public function snapshot(): PublicLocaleSnapshot
    {
        return PublicLocaleSnapshot::fromRows([
            ['code' => 'cs', 'enabled' => 1, 'is_default' => 1],
        ]);
    }
}

/**
 * Simulates database changes during subtree reservations
 */
final class BatchReservationConnection implements ConnectionInterface
{
    public int $transactions = 0;

    /**
     * Receives canonical route rows for the batch scenario
     *
     * @param list<array{id:int,module_code:string,entity_id:int,locale:string,path:string}> $rows
     */
    public function __construct(public array $rows) {}

    public function select(string $sql, array $bindings = []): array
    {
        if (!str_contains($sql, 'module_code = ?')) {
            return array_values(array_filter(
                $this->rows,
                static fn(array $row): bool => $row['locale'] === ($bindings[0] ?? null),
            ));
        }

        return array_values(array_filter(
            $this->rows,
            static fn(array $row): bool => $row['module_code'] === ($bindings[0] ?? null)
                && $row['entity_id'] === ($bindings[1] ?? null)
                && $row['locale'] === ($bindings[2] ?? null),
        ));
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        foreach ($this->rows as &$row) {
            if ($row['module_code'] === ($bindings[2] ?? null)
                && $row['entity_id'] === ($bindings[3] ?? null)
                && $row['locale'] === ($bindings[4] ?? null)
            ) {
                $row['path'] = (string) $bindings[0];
            }
        }
        unset($row);

        return 1;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        ++$this->transactions;

        return $callback($this);
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}

/**
 * Simulates competing canonical path reservations
 */
final class ReservationConnection implements ConnectionInterface
{
    /**
     * Paths attempted during reservation
     *
     * @var list<string>
     */
    public array $attemptedPaths = [];

    /**
     * Receives paths reserved by a competing target
     *
     * @param list<string> $occupiedPaths
     */
    public function __construct(
        private int $failuresRemaining,
        private readonly ?string $targetPath = null,
        private array $occupiedPaths = [],
    ) {}

    public function select(string $sql, array $bindings = []): array
    {
        if (str_contains($sql, 'module_code = ?')) {
            return $this->targetPath === null ? [] : [[
                'id' => 1,
                'module_code' => (string) ($bindings[0] ?? ''),
                'entity_id' => (int) ($bindings[1] ?? 0),
                'locale' => (string) ($bindings[2] ?? ''),
                'path' => $this->targetPath,
            ]];
        }

        return array_map(
            static fn(string $path, int $index): array => [
                'id' => $index + 1,
                'module_code' => 'cms.page',
                'entity_id' => $index + 100,
                'locale' => 'cs',
                'path' => $path,
            ],
            $this->occupiedPaths,
            array_keys($this->occupiedPaths),
        );
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $path = (string) ($bindings[3] ?? '');
        $this->attemptedPaths[] = $path;
        if ($this->failuresRemaining > 0) {
            --$this->failuresRemaining;
            $this->occupiedPaths[] = $path;

            throw DatabaseException::queryFailed(
                $sql,
                "Duplicate entry 'cs-{$path}' for key 'uq_cms_route_locale_path'",
            );
        }

        return 1;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}
