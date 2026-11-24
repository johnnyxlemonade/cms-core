<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

/**
 * Defines the public CMS route repository contract
 */
interface CmsRouteRepositoryInterface
{
    /**
     * Finds a public CMS route by path
     */
    public function find(string $locale, string $path): ?CmsRoute;
}
