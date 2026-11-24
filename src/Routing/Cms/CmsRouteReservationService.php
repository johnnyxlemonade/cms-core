<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Framework\Database\Exception\DatabaseException;
use RuntimeException;

/**
 * Reserves canonical CMS paths for module targets
 */
final readonly class CmsRouteReservationService
{
    private const MAXIMUM_RESERVATION_ATTEMPTS = 4;

    private const MAXIMUM_PATH_LENGTH = 700;

    /**
     * Receives route persistence and path reservation dependencies
     */
    public function __construct(
        private CmsRouteRepository $routes,
        private CmsRouteCandidateResolver $candidates,
        private CmsReservedPathGuard $reservedPaths,
    ) {}

    /**
     * Reports whether a target already owns a canonical route in a locale
     */
    public function hasReservation(string $moduleCode, int $entityId, string $locale): bool
    {
        return $this->routes->findForTarget($moduleCode, $entityId, $locale) !== null;
    }

    /**
     * Returns the current canonical path for a target
     */
    public function reservationPath(string $moduleCode, int $entityId, string $locale): ?string
    {
        return $this->routes->findForTarget($moduleCode, $entityId, $locale)?->getPath();
    }

    /**
     * Reserves an available path with bounded retries on concurrent writes
     */
    public function reserveAutomatic(
        string $moduleCode,
        int $entityId,
        string $locale,
        string $baseSlug,
        int $maximumSlugLength = 255,
        string $pathPrefix = '',
    ): CmsRouteReservation {
        $pathPrefix = $this->normalizedPathPrefix($pathPrefix);
        $prefixLength = $pathPrefix === '' ? 0 : strlen($pathPrefix) + 1;
        $maximumSlugLength = min($maximumSlugLength, self::MAXIMUM_PATH_LENGTH - $prefixLength);
        if ($maximumSlugLength < 1) {
            throw new CmsRouteValidationException('cms.route.path_invalid');
        }
        for ($attempt = 1; $attempt <= self::MAXIMUM_RESERVATION_ATTEMPTS; $attempt++) {
            $reservation = $this->candidates->resolve(
                moduleCode: $moduleCode,
                entityId: $entityId,
                baseSlug: $baseSlug,
                routes: $this->routes->routesForLocale($locale),
                maximumSlugLength: $maximumSlugLength,
                isReserved: $this->reservedPaths->isReserved(...),
                pathPrefix: $pathPrefix,
            );
            try {
                $this->routes->reserve(
                    moduleCode: $moduleCode,
                    entityId: $entityId,
                    locale: $locale,
                    path: $reservation->getPath(),
                );

                return $reservation;
            } catch (DatabaseException $exception) {
                if (!$this->isUniqueConflict($exception) || $attempt === self::MAXIMUM_RESERVATION_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('CMS route reservation retry limit was reached.');
    }

    /**
     * Reserves an explicit path or rejects an invalid or occupied path
     */
    public function reserveExplicit(
        string $moduleCode,
        int $entityId,
        string $locale,
        string $path,
    ): CmsRouteReservation {
        $path = $this->validatedPath($path);
        $this->assertAvailable($moduleCode, $entityId, $locale, $path);

        try {
            $this->routes->reserve($moduleCode, $entityId, $locale, $path);
        } catch (DatabaseException $exception) {
            if ($this->isUniqueConflict($exception)) {
                throw new CmsRouteValidationException('cms.route.path_occupied', previous: $exception);
            }

            throw $exception;
        }

        return new CmsRouteReservation(basename($path), $path);
    }

    /**
     * Atomically replaces a validated set of subtree routes
     *
     * @param non-empty-list<array{entityId:int,locale:string,path:string}> $replacements
     */
    public function replaceExplicitBatch(string $moduleCode, array $replacements): void
    {
        $normalized = [];
        $targets = [];
        $paths = [];
        foreach ($replacements as $replacement) {
            $path = $this->validatedPath($replacement['path']);
            $targetKey = $replacement['locale'] . ':' . $replacement['entityId'];
            $pathKey = $replacement['locale'] . ':' . $path;
            if (isset($targets[$targetKey]) || isset($paths[$pathKey])) {
                throw new CmsRouteValidationException('cms.route.batch_invalid');
            }
            $targets[$targetKey] = true;
            $paths[$pathKey] = true;
            $normalized[] = [
                'entityId' => $replacement['entityId'],
                'locale' => $replacement['locale'],
                'path' => $path,
            ];
        }

        try {
            $this->routes->transaction(function () use ($moduleCode, $normalized, $targets): void {
                foreach ($normalized as $replacement) {
                    if ($this->routes->findForTarget(
                        $moduleCode,
                        $replacement['entityId'],
                        $replacement['locale'],
                    ) === null) {
                        throw new CmsRouteValidationException('cms.route.batch_invalid');
                    }
                    if ($this->reservedPaths->isReserved($replacement['path'])) {
                        throw new CmsRouteValidationException('cms.route.path_reserved');
                    }
                    foreach ($this->routes->routesForLocale($replacement['locale']) as $route) {
                        $ownerKey = $route->getLocale() . ':' . $route->getEntityId();
                        if ($route->getPath() === $replacement['path']
                            && !($route->getModuleCode() === $moduleCode && isset($targets[$ownerKey]))
                        ) {
                            throw new CmsRouteValidationException('cms.route.path_occupied');
                        }
                    }
                }

                $now = date('Y-m-d H:i:s');
                $nonce = bin2hex(random_bytes(12));
                foreach ($normalized as $index => $replacement) {
                    $this->routes->updatePath(
                        $moduleCode,
                        $replacement['entityId'],
                        $replacement['locale'],
                        '__cms_move_' . $nonce . '_' . $index,
                        $now,
                    );
                }
                foreach ($normalized as $replacement) {
                    $this->routes->updatePath(
                        $moduleCode,
                        $replacement['entityId'],
                        $replacement['locale'],
                        $replacement['path'],
                        $now,
                    );
                }
            });
        } catch (DatabaseException $exception) {
            if ($this->isUniqueConflict($exception)) {
                throw new CmsRouteValidationException('cms.route.path_occupied', previous: $exception);
            }

            throw $exception;
        }
    }

    /**
     * Hides routes of a deleted entity while preserving their reservations
     */
    public function softDeleteForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->routes->softDeleteForTarget($moduleCode, $entityId, $now);
    }

    /**
     * Reactivates previously reserved routes of a restored entity
     */
    public function restoreForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->routes->restoreForTarget($moduleCode, $entityId, $now);
    }

    /**
     * Detects a unique key conflict caused by a concurrent reservation
     */
    private function isUniqueConflict(DatabaseException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'unique constraint');
    }

    /**
     * Normalizes and validates a nonroot CMS path
     */
    private function validatedPath(string $path): string
    {
        $path = trim(trim($path), '/');
        if ($path === ''
            || strlen($path) > self::MAXIMUM_PATH_LENGTH
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/D', $path) !== 1
        ) {
            throw new CmsRouteValidationException('cms.route.path_invalid');
        }
        if ($this->reservedPaths->isReserved($path)) {
            throw new CmsRouteValidationException('cms.route.path_reserved');
        }

        return $path;
    }

    /**
     * Normalizes the optional directory of an automatically reserved canonical path
     */
    private function normalizedPathPrefix(string $pathPrefix): string
    {
        $pathPrefix = trim(trim($pathPrefix), '/');
        if ($pathPrefix !== '' && (
            strlen($pathPrefix) >= self::MAXIMUM_PATH_LENGTH
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/D', $pathPrefix) !== 1
        )) {
            throw new CmsRouteValidationException('cms.route.path_invalid');
        }

        return $pathPrefix;
    }

    /**
     * Checks that another target does not own the path in the same locale
     */
    private function assertAvailable(string $moduleCode, int $entityId, string $locale, string $path): void
    {
        foreach ($this->routes->routesForLocale($locale) as $route) {
            if ($route->getPath() !== $path) {
                continue;
            }
            if ($route->getModuleCode() === $moduleCode && $route->getEntityId() === $entityId) {
                return;
            }

            throw new CmsRouteValidationException('cms.route.path_occupied');
        }
    }
}
