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
     * Lazy handlers shared by dynamic module code families
     *
     * @var array<string,Closure(ContainerInterface):PublicCmsRouteHandlerInterface>
     */
    private array $prefixFactories = [];

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
     * Registers one lazy handler factory for a dynamic module code prefix
     *
     * @param Closure(ContainerInterface):PublicCmsRouteHandlerInterface $handler
     */
    public function registerPrefixFactory(string $moduleCodePrefix, Closure $handler): void
    {
        if ($moduleCodePrefix === '') {
            throw new \InvalidArgumentException('CMS module code prefix must not be empty.');
        }

        foreach (array_keys($this->prefixFactories) as $registeredPrefix) {
            if ($registeredPrefix !== $moduleCodePrefix
                && (str_starts_with($registeredPrefix, $moduleCodePrefix) || str_starts_with($moduleCodePrefix, $registeredPrefix))) {
                throw new \InvalidArgumentException('CMS module code prefixes must not overlap.');
            }
        }

        $this->prefixFactories[$moduleCodePrefix] = $handler;
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

        if ($handler !== null) {
            return $handler;
        }

        foreach ($this->prefixFactories as $prefix => $factory) {
            if (str_starts_with($moduleCode, $prefix)) {
                return $factory($container);
            }
        }

        return null;
    }
}
