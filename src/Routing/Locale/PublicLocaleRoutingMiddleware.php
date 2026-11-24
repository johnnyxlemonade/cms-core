<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Applies public locale resolution before matching content routes
 */
final readonly class PublicLocaleRoutingMiddleware implements MiddlewareInterface
{
    public const string BYPASS_ATTRIBUTE = 'lemonade.public_locale_bypass';

    /**
     * Receives the lazy locale resolver and Framework response factory
     */
    public function __construct(
        private PublicLocaleResolver $resolver,
        private ResponseFactoryInterface $responses,
    ) {}

    /**
     * Redirects to the canonical path or passes a normalized path to the router
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            $request->getAttribute(self::BYPASS_ATTRIBUTE) === true
            || str_starts_with($request->getUri()->getPath(), '/api/')
        ) {
            return $handler->handle($request);
        }

        $resolution = $this->resolver->resolve();
        if ($this->isTechnicalApiPath($resolution->getPath()) || $this->isTechnicalApiPath($resolution->getRedirectTo())) {
            return $this->responses->createResponse(HttpStatus::NOT_FOUND->value);
        }
        $redirectTo = $resolution->getRedirectTo();
        if ($redirectTo !== null) {
            $query = $request->getUri()->getQuery();

            return $this->responses
                ->createResponse(HttpStatus::MOVED_PERMANENTLY->value)
                ->withHeader('Location', $query === '' ? $redirectTo : $redirectTo . '?' . $query);
        }
        $locale = $resolution->getLocale();
        $path = $resolution->getPath();
        if ($locale === null || $path === null) {
            throw NotFoundHttpException::create();
        }
        if ($locale === $resolution->getDefaultLocale()) {
            return $handler->handle($request);
        }

        return $handler->handle($request->withAttribute(
            RouteRequestAttributes::DISPATCH_PATH,
            '/' . ltrim($path, '/'),
        ));
    }

    /**
     * Distinguishes technical API paths from locale-aware public content
     */
    private function isTechnicalApiPath(?string $path): bool
    {
        return $path !== null && str_starts_with('/' . ltrim($path, '/'), '/api/');
    }
}
