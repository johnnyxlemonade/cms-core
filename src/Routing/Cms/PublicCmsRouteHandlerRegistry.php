<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Closure;
use Lemonade\Framework\Container\ContainerInterface;

/**
 * Holds handlers contributed by modules for public CMS routes
 */
final class PublicCmsRouteHandlerRegistry
{
    /**
     * Registered handlers or factories keyed by owning module
     *
     * @var array<string, PublicCmsRouteHandlerInterface|Closure(ContainerInterface):PublicCmsRouteHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Assigns a handler to a module with an active public route
     */
    public function register(string $moduleCode, PublicCmsRouteHandlerInterface $handler): void
    {
        $this->handlers[$moduleCode] = $handler;
    }

    /**
     * Registers a lazy factory for an optional CMS detail handler
     *
     * @param Closure(ContainerInterface):PublicCmsRouteHandlerInterface $handler
     */
    public function registerFactory(string $moduleCode, Closure $handler): void
    {
        $this->handlers[$moduleCode] = $handler;
    }

    /**
     * Returns the handler contributed for a module
     */
    public function handlerFor(string $moduleCode, ContainerInterface $container): ?PublicCmsRouteHandlerInterface
    {
        $handler = $this->handlers[$moduleCode] ?? null;
        if ($handler instanceof Closure) {
            return $handler($container);
        }

        return $handler;
    }
}
