<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Uplatni public locale resolution pred matchingem vsech verejnych rout
 */
final readonly class PublicLocaleRoutingMiddleware implements MiddlewareInterface
{
    /**
     * Nastavuje sdileny request-scoped locale vysledek a framework response factory
     */
    public function __construct(
        private PublicLocaleResolution $locale,
        private ResponseFactoryInterface $responses,
    ) {}

    /**
     * Redirectuje canonical cestu nebo preda normalizovanou dispatch cestu Routeru
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $redirectTo = $this->locale->redirectTo();
        if ($redirectTo !== null) {
            $query = $request->getUri()->getQuery();

            return $this->responses
                ->createResponse(301)
                ->withHeader('Location', $query === '' ? $redirectTo : $redirectTo . '?' . $query);
        }
        $locale = $this->locale->locale();
        $path = $this->locale->path();
        if ($locale === null || $path === null) {
            throw NotFoundHttpException::create();
        }
        if ($locale === $this->locale->defaultLocale()) {
            return $handler->handle($request);
        }

        return $handler->handle($request->withAttribute(
            RouteRequestAttributes::DISPATCH_PATH,
            '/' . ltrim($path, '/'),
        ));
    }
}
