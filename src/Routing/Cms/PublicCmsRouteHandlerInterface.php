<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;

use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Definuje handler verejne CMS routy
 */
interface PublicCmsRouteHandlerInterface
{
    /**
     * Obslouzi verejnou CMS cestu nebo vrati prazdny vysledek
     */
    public function handle(
        int $entityId,
        PublicLocaleResolution $resolution,
        CmsRoute $route,
        ViewRendererInterface $views,
    ): ?ResponseInterface;
}
