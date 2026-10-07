<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

use Closure;

/**
 * Drzi handlery, ktere moduly prispely pro verejne collection routy
 */
final class PublicCmsCollectionHandlerRegistry
{
    /** @var array<string, PublicCmsCollectionHandlerInterface|Closure():PublicCmsCollectionHandlerInterface> */
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
     * @param Closure():PublicCmsCollectionHandlerInterface $handler
     */
    public function registerFactory(string $moduleCode, Closure $handler): void
    {
        $this->handlers[$moduleCode] = $handler;
    }

    /**
     * Vrati collection handler prispely pro dany modul
     */
    public function handlerFor(string $moduleCode): ?PublicCmsCollectionHandlerInterface
    {
        $handler = $this->handlers[$moduleCode] ?? null;
        if ($handler instanceof Closure) {
            $handler = $handler();
            $this->handlers[$moduleCode] = $handler;
        }

        return $handler;
    }
}
