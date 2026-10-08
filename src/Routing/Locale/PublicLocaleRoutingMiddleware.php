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
    public const BYPASS_ATTRIBUTE = 'lemonade.public_locale_bypass';

    /**
     * Nastavuje lazy locale resolver a framework response factory
     */
    public function __construct(
        private PublicLocaleResolver $resolver,
        private ResponseFactoryInterface $responses,
    ) {}

    /**
     * Redirectuje canonical cestu nebo preda normalizovanou dispatch cestu Routeru
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute(self::BYPASS_ATTRIBUTE) === true) {
            return $handler->handle($request);
        }

        $resolution = $this->resolver->resolve();
        $redirectTo = $resolution->redirectTo();
        if ($redirectTo !== null) {
            $query = $request->getUri()->getQuery();

            return $this->responses
                ->createResponse(301)
                ->withHeader('Location', $query === '' ? $redirectTo : $redirectTo . '?' . $query);
        }
        $locale = $resolution->locale();
        $path = $resolution->path();
        if ($locale === null || $path === null) {
            throw NotFoundHttpException::create();
        }
        if ($locale === $resolution->defaultLocale()) {
            return $handler->handle($request);
        }

        return $handler->handle($request->withAttribute(
            RouteRequestAttributes::DISPATCH_PATH,
            '/' . ltrim($path, '/'),
        ));
    }
}
