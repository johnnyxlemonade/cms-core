<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\CmsRouteCandidateResolver;
use Lemonade\Cms\Routing\Cms\CmsRouteRepository;
use Lemonade\Cms\Routing\Cms\CmsRouteReservationService;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

/**
 * Overuje omezeny retry canonical rezervace bez databazove infrastruktury
 */
final class CmsRouteReservationServiceTest extends TestCase
{
    /**
     * Existujici target je rozpoznan bez nove rezervace canonical cesty
     */
    public function testRecognizesExistingTargetReservation(): void
    {
        $connection = new ReservationConnection(0, 'stable-path');
        $service = $this->service($connection);

        self::assertTrue($service->hasReservation('cms.news', 7, 'cs'));
        self::assertSame([], $connection->attemptedPaths);
    }

    /**
     * Po soubezne kolizi znovu nacte routy a vybere nasledujici suffix
     */
    public function testRetriesUniqueConflictWithNextCandidate(): void
    {
        $connection = new ReservationConnection(1);
        $service = $this->service($connection);

        $reservation = $service->reserveAutomatic('cms.news', 7, 'cs', 'test');

        self::assertSame('test-1', $reservation->slug());
        self::assertSame(['test', 'test-1'], $connection->attemptedPaths);
    }

    /**
     * Trvala kolize skonci po pevnem poctu pokusu
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

    private function service(ReservationConnection $connection): CmsRouteReservationService
    {
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));

        return new CmsRouteReservationService(
            new CmsRouteRepository($database),
            new CmsRouteCandidateResolver(),
        );
    }
}

final class ReservationConnection implements ConnectionInterface
{
    /** @var list<string> */
    public array $attemptedPaths = [];

    /** @var list<string> */
    private array $occupiedPaths = [];

    public function __construct(
        private int $failuresRemaining,
        private readonly ?string $targetPath = null,
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
