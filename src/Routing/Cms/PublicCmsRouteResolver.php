<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves a public CMS path to its active module handler
 */
final readonly class PublicCmsRouteResolver
{
    /**
     * Receives locale resolution, canonical routes and module handlers
     */
    public function __construct(
        private PublicLocaleResolution             $locale,
        private CmsRouteRepositoryInterface        $routes,
        private PublicModuleStateResolverInterface $modules,
        private PublicCmsRouteHandlerRegistry      $handlers,
        private ResponseFactoryInterface           $responses,
        private ViewRendererInterface              $views,
        private ServerRequestInterface             $request,
        private ContainerInterface                 $container,
    ) {}

    /**
     * Returns a redirect, handler response or not found result
     */
    public function resolve(): ResponseInterface
    {
        $locale = $this->locale;
        $redirectTo = $locale->getRedirectTo();
        if ($redirectTo !== null) {
            $queryString = $this->request->getUri()->getQuery();
            $location = $queryString === '' ? $redirectTo : $redirectTo . '?' . $queryString;

            return $this->responses->createResponse(HttpStatus::MOVED_PERMANENTLY->value)->withHeader('Location', $location);
        }
        $routeLocale = $locale->getLocale();
        $routePath = $locale->getPath();
        if ($routeLocale === null || $routePath === null || $routePath === '') {
            return $this->notFound();
        }

        $route = $this->routes->find($routeLocale, $routePath);
        if ($route === null || !$this->modules->isDiscoveredInstalledAndEnabled($route->getModuleCode())) {
            return $this->notFound();
        }

        $handler = $this->handlers->handlerFor($route->getModuleCode(), $this->container);
        if ($handler === null) {
            return $this->notFound();
        }

        return $handler->handle($route->getEntityId(), $locale, $route, $this->views) ?? $this->notFound();
    }

    /**
     * Creates a response for an invalid or unavailable CMS route
     */
    private function notFound(): never
    {
        throw NotFoundHttpException::create();
    }
}
