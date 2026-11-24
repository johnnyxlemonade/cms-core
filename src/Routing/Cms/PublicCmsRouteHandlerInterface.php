<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines a public CMS route handler
 */
interface PublicCmsRouteHandlerInterface
{
    /**
     * Handles a public CMS route or returns no result
     */
    public function handle(
        int $entityId,
        PublicLocaleResolution $resolution,
        CmsRoute $route,
        ViewRendererInterface $views,
    ): ?ResponseInterface;
}
