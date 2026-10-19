<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Chrani systemove a hostem registrovane cesty pred CMS fallbackem
 */
final readonly class CmsReservedPathGuard
{
    private const CMS_FALLBACK_ROUTE = 'frontend.cms-route';

    /** @var list<string> */
    private const SYSTEM_SEGMENTS = ['api', 'assets', 'static'];

    /**
     * Nastavuje skutecny host router a aktualni request pro bezstavovou kontrolu GET cesty
     */
    public function __construct(
        private Router $router,
        private ServerRequestInterface $request,
        private PublicLocaleRegistryInterface $locales,
    ) {}

    /**
     * Rozhodne, zda cestu vlastni host nebo systemovy namespace
     */
    public function isReserved(string $path): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return true;
        }

        $segment = strtok($path, '/');
        $firstSegment = strtolower($segment === false ? '' : $segment);
        if (in_array($firstSegment, self::SYSTEM_SEGMENTS, true)) {
            return true;
        }
        if ($this->locales->snapshot()->isKnownLocale($firstSegment)) {
            return true;
        }

        $uri = $this->request->getUri()->withPath('/' . $path)->withQuery('')->withFragment('');
        try {
            $match = $this->router->match($this->request->withMethod('GET')->withUri($uri));
        } catch (RouteNotFoundException) {
            return false;
        }

        return $match->name() !== self::CMS_FALLBACK_ROUTE;
    }
}
