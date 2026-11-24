<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Module;

/**
 * Defines the public module state resolver contract
 */
interface PublicModuleStateResolverInterface
{
    /**
     * Checks whether a module is discovered, installed and enabled
     */
    public function isDiscoveredInstalledAndEnabled(string $moduleCode): bool;
}
