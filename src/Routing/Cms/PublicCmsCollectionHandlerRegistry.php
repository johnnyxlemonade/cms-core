<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Closure;
use Lemonade\Framework\Container\ContainerInterface;

/**
 * Drzi handlery, ktere moduly prispely pro verejne collection routy
 */
final class PublicCmsCollectionHandlerRegistry
{
    /** @var array<string, PublicCmsCollectionHandlerInterface|Closure(ContainerInterface):PublicCmsCollectionHandlerInterface> */
    private array $handlers = [];

    /**
     * Priradi collection handler k modulu s aktivnim verejnym prefixem
     */
    public function register(string $moduleCode, PublicCmsCollectionHandlerInterface $handler): void
    {
        $this->handlers[$moduleCode] = $handler;
    }

    /**
     * Registruje lazy factory collection handleru optional CMS modulu
     *
     * @param Closure(ContainerInterface):PublicCmsCollectionHandlerInterface $handler
     */
    public function registerFactory(string $moduleCode, Closure $handler): void
    {
        $this->handlers[$moduleCode] = $handler;
    }

    /**
     * Vrati collection handler prispely pro dany modul
     */
    public function handlerFor(string $moduleCode, ContainerInterface $container): ?PublicCmsCollectionHandlerInterface
    {
        $handler = $this->handlers[$moduleCode] ?? null;
        if ($handler instanceof Closure) {
            return $handler($container);
        }

        return $handler;
    }
}
