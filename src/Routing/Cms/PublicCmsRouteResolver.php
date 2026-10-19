<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Preklada verejnou CMS cestu na handler aktivniho modulu
 */
final readonly class PublicCmsRouteResolver
{
    /**
     * Nastavuje locale, canonical routy a prispene handlery modulu
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
     * Vrati redirect, odpoved handleru nebo not found pro verejnou cestu
     */
    public function resolve(): ResponseInterface
    {
        $locale = $this->locale;
        $redirectTo = $locale->redirectTo();
        if ($redirectTo !== null) {
            $queryString = $this->request->getUri()->getQuery();
            $location = $queryString === '' ? $redirectTo : $redirectTo . '?' . $queryString;

            return $this->responses->createResponse(301)->withHeader('Location', $location);
        }
        $routeLocale = $locale->locale();
        $routePath = $locale->path();
        if ($routeLocale === null || $routePath === null || $routePath === '') {
            return $this->notFound();
        }

        $route = $this->routes->find($routeLocale, $routePath);
        if ($route === null || !$this->modules->isDiscoveredInstalledAndEnabled($route->moduleCode())) {
            return $this->notFound();
        }

        $handler = $this->handlers->handlerFor($route->moduleCode(), $this->container);
        if ($handler === null) {
            return $this->notFound();
        }

        return $handler->handle($route->entityId(), $locale, $route, $this->views) ?? $this->notFound();
    }

    /**
     * Vytvori odpoved pro neplatnou nebo nedostupnou CMS routu
     */
    private function notFound(): never
    {
        throw NotFoundHttpException::create();
    }
}
