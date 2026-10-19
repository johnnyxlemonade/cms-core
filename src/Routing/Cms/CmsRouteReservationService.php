<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Framework\Database\Exception\DatabaseException;
use RuntimeException;

/**
 * Resolvuje a bezpecne rezervuje canonical CMS cestu pro jeden module target
 */
final class CmsRouteReservationService
{
    private const MAXIMUM_RESERVATION_ATTEMPTS = 4;

    /**
     * Nastavuje persistence rout a resolver volnych suffix kandidatu
     */
    public function __construct(
        private readonly CmsRouteRepository $routes,
        private readonly CmsRouteCandidateResolver $candidates,
        private readonly CmsReservedPathGuard $reservedPaths,
    ) {}

    /**
     * Rozhodne, zda target jiz vlastni canonical route v pozadovanem locale
     */
    public function hasReservation(string $moduleCode, int $entityId, string $locale): bool
    {
        return $this->routes->findForTarget($moduleCode, $entityId, $locale) !== null;
    }

    /**
     * Vybere volny candidate a target-safe jej rezervuje s omezenym retry pri soubehu
     */
    public function reserveAutomatic(
        string $moduleCode,
        int $entityId,
        string $locale,
        string $baseSlug,
        int $maximumSlugLength = 255,
    ): CmsRouteReservation {
        for ($attempt = 1; $attempt <= self::MAXIMUM_RESERVATION_ATTEMPTS; $attempt++) {
            $reservation = $this->candidates->resolve(
                moduleCode: $moduleCode,
                entityId: $entityId,
                baseSlug: $baseSlug,
                routes: $this->routes->routesForLocale($locale),
                maximumSlugLength: $maximumSlugLength,
                isReserved: $this->reservedPaths->isReserved(...),
            );
            try {
                $this->routes->reserve(
                    moduleCode: $moduleCode,
                    entityId: $entityId,
                    locale: $locale,
                    path: $reservation->path(),
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
     * Rezervuje explicitni cestu nebo ji odmitne jako neplatnou ci obsazenou
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
     * Atomicky prepise existujici routy podstromu po predchozi kontrole cele sady
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
                        $ownerKey = $route->locale() . ':' . $route->entityId();
                        if ($route->path() === $replacement['path']
                            && !($route->moduleCode() === $moduleCode && isset($targets[$ownerKey]))
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
     * Skryje routy smazane entity bez uvolneni jejich URL adres
     */
    public function softDeleteForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->routes->softDeleteForTarget($moduleCode, $entityId, $now);
    }

    /**
     * Znovu aktivuje driv rezervovane routy obnovene entity
     */
    public function restoreForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->routes->restoreForTarget($moduleCode, $entityId, $now);
    }

    /**
     * Rozpozna databazovou kolizi unikatu, ktera muze vzniknout jen pri soubehu
     */
    private function isUniqueConflict(DatabaseException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'unique constraint');
    }

    /**
     * Normalizuje a overi ulozitelnou nerootovou CMS cestu
     */
    private function validatedPath(string $path): string
    {
        $path = trim(trim($path), '/');
        if ($path === ''
            || strlen($path) > 700
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
     * Overi, ze explicitni cesta nepatri jinemu targetu ve stejnem locale
     */
    private function assertAvailable(string $moduleCode, int $entityId, string $locale, string $path): void
    {
        foreach ($this->routes->routesForLocale($locale) as $route) {
            if ($route->path() !== $path) {
                continue;
            }
            if ($route->moduleCode() === $moduleCode && $route->entityId() === $entityId) {
                return;
            }

            throw new CmsRouteValidationException('cms.route.path_occupied');
        }
    }
}
