<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Protects system and host routes from the CMS fallback
 */
final readonly class CmsReservedPathGuard
{
    private const CMS_FALLBACK_ROUTE = 'frontend.cms-route';

    /**
     * System path segments reserved before host routing
     *
     * @var list<string>
     */
    private const SYSTEM_SEGMENTS = ['api', 'assets', 'static'];

    /**
     * Receives the host router and current request for stateless GET checks
     */
    public function __construct(
        private Router $router,
        private ServerRequestInterface $request,
        private PublicLocaleRegistryInterface $locales,
    ) {}

    /**
     * Reports whether the host or a system namespace owns a path
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
