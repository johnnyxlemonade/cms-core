<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

/**
 * Defines the public locale registry contract
 */
interface PublicLocaleRegistryInterface
{
    /**
     * Returns an atomic language snapshot for one public locale resolution
     */
    public function snapshot(): PublicLocaleSnapshot;
}
