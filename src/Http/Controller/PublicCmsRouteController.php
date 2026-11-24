<?php

declare(strict_types=1);

namespace Lemonade\Cms\Http\Controller;

use Lemonade\Cms\Routing\Cms\PublicCmsRouteResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Passes a public CMS path to the module-neutral resolver
 */
final readonly class PublicCmsRouteController
{
    /**
     * Receives the public CMS route resolver
     */
    public function __construct(
        private PublicCmsRouteResolver $resolver,
    ) {}

    /**
     * Passes the current URL to the public CMS route resolver
     */
    public function show(string $path, ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolver->resolve();
    }
}
