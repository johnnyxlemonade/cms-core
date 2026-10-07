<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

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
        string $prefix,
        string $baseSlug,
    ): CmsRouteReservation {
        for ($attempt = 1; $attempt <= self::MAXIMUM_RESERVATION_ATTEMPTS; $attempt++) {
            $reservation = $this->candidates->resolve(
                moduleCode: $moduleCode,
                entityId: $entityId,
                prefix: $prefix,
                baseSlug: $baseSlug,
                routes: $this->routes->routesForPrefix($locale, $prefix),
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
}
