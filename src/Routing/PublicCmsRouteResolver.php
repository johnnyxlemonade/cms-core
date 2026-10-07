<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Preklada verejnou CMS cestu na handler aktivniho modulu
 */
final class PublicCmsRouteResolver
{
    /**
     * Nastavuje locale, canonical routy a prispene handlery modulu
     */
    public function __construct(
        private readonly PublicLocaleResolver $locales,
        private readonly CmsRouteRepositoryInterface $routes,
        private readonly PublicModuleStateResolverInterface $modules,
        private readonly PublicModuleRoutePrefixRepositoryInterface $prefixes,
        private readonly PublicCmsRouteHandlerRegistry $handlers,
        private readonly PublicCmsCollectionHandlerRegistry $collections,
        private readonly ResponseFactoryInterface $responses,
        private readonly ViewRendererInterface $views,
    ) {}

    /**
     * Vrati redirect, odpoved handleru nebo not found pro verejnou cestu
     */
    public function resolve(string $requestPath, string $queryString = ''): ResponseInterface
    {
        $locale = $this->locales->resolve($requestPath);
        $redirectTo = $locale->redirectTo();
        if ($redirectTo !== null) {
            $location = $queryString === '' ? $redirectTo : $redirectTo . '?' . $queryString;

            return $this->responses->createResponse(301)->withHeader('Location', $location);
        }
        $routeLocale = $locale->locale();
        $routePath = $locale->path();
        if ($routeLocale === null || $routePath === null || $routePath === '') {
            return $this->notFound();
        }

        $collectionResponse = $this->resolveCollection($routeLocale, $routePath);
        if ($collectionResponse !== null) {
            return $collectionResponse;
        }

        $route = $this->routes->find($routeLocale, $routePath);
        if ($route === null || !$this->modules->isDiscoveredInstalledAndEnabled($route->moduleCode())) {
            return $this->notFound();
        }

        $prefix = $this->prefixes->prefixFor($route->moduleCode(), $route->locale());
        if ($prefix === null || $this->firstSegment($route->path()) !== $prefix) {
            return $this->notFound();
        }

        $handler = $this->handlers->handlerFor($route->moduleCode());
        if ($handler === null) {
            return $this->notFound();
        }

        return $handler->handle($route->entityId(), $route->locale(), $route, $this->views) ?? $this->notFound();
    }

    /**
     * Preda cestu shodnou s runtime prefixem collection handleru modulu
     */
    private function resolveCollection(string $locale, string $path): ?ResponseInterface
    {
        if (str_contains($path, '/')) {
            return null;
        }
        $moduleCode = $this->prefixes->moduleFor($locale, $path);
        if ($moduleCode === null || !$this->modules->isDiscoveredInstalledAndEnabled($moduleCode)) {
            return null;
        }

        $handler = $this->collections->handlerFor($moduleCode);

        return $handler?->handleCollection($locale, $path, $this->views);
    }

    /**
     * Vrati prvni segment kanonicke cesty modulu
     */
    private function firstSegment(string $path): string
    {
        return explode('/', $path, 2)[0];
    }

    /**
     * Vytvori odpoved pro neplatnou nebo nedostupnou CMS routu
     */
    private function notFound(): never
    {
        throw NotFoundHttpException::create();
    }
}
