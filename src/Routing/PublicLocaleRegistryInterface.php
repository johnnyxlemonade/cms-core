<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

/**
 * Definuje registr verejnych lokalizaci
 */
interface PublicLocaleRegistryInterface
{
    /**
     * Vrati vychozi verejnou lokalizaci
     */
    public function defaultLocale(): string;

    /**
     * Overi dostupnost nepovinne vychozi lokalizace
     */
    public function isEnabledNonDefault(string $locale): bool;

    /**
     * Overi, zda je lokalizace znama
     */
    public function isKnownLocale(string $locale): bool;
}
