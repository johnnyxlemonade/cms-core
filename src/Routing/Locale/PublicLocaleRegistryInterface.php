<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

/**
 * Definuje registr verejnych lokalizaci
 */
interface PublicLocaleRegistryInterface
{
    /**
     * Vrati atomicky snapshot jazyku pro jedno verejne locale resolution
     */
    public function snapshot(): PublicLocaleSnapshot;
}
