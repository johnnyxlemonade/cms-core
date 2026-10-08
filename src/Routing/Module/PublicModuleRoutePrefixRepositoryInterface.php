<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Module;

/**
 * Definuje uloziste verejnych prefixu modulu
 */
interface PublicModuleRoutePrefixRepositoryInterface
{
    /**
     * Vrati verejny prefix routy modulu
     */
    public function prefixFor(string $moduleCode, string $locale): ?string;

    /**
     * Vrati modul vlastnici dany lokalizovany verejny prefix
     */
    public function moduleFor(string $locale, string $prefix): ?string;
}
