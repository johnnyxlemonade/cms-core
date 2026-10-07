<?php

declare(strict_types=1);

namespace Lemonade\Cms\Http\Controller;

use Lemonade\Cms\Routing\PublicCmsRouteResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Predava verejnou CMS cestu package-neutralnimu resolveru modulu
 */
final class PublicCmsRouteController
{
    /**
     * Nastavuje resolver verejnych CMS cest
     */
    public function __construct(
        private readonly PublicCmsRouteResolver $resolver,
    ) {}

    /**
     * Predava aktualni URL resolveru verejne CMS cesty
     */
    public function show(string $path, ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolver->resolve(
            $request->getUri()->getPath(),
            $request->getUri()->getQuery(),
        );
    }
}
