<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

/**
 * Definuje uloziste verejnych CMS rout
 */
interface CmsRouteRepositoryInterface
{
    /**
     * Najde verejnou CMS routu podle cesty
     */
    public function find(string $locale, string $path): ?CmsRoute;
}
