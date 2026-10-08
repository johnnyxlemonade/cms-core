<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Module;

/**
 * Definuje zdroj verejneho stavu modulu
 */
interface PublicModuleStateResolverInterface
{
    /**
     * Overi, zda je modul objeveny, nainstalovany a aktivni
     */
    public function isDiscoveredInstalledAndEnabled(string $moduleCode): bool;
}
