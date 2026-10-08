<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Closure;

/**
 * Drzi handlery, ktere moduly prispely pro verejne CMS routy
 */
final class PublicCmsRouteHandlerRegistry
{
    /** @var array<string, PublicCmsRouteHandlerInterface|Closure():PublicCmsRouteHandlerInterface> */
    private array $handlers = [];

    /**
     * Priradi handler k modulu s aktivni verejnou routou
     */
    public function register(string $moduleCode, PublicCmsRouteHandlerInterface $handler): void
    {
        $this->handlers[$moduleCode] = $handler;
    }

    /**
     * Registruje lazy factory detail handleru optional CMS modulu
     *
     * @param Closure():PublicCmsRouteHandlerInterface $handler
     */
    public function registerFactory(string $moduleCode, Closure $handler): void
    {
        $this->handlers[$moduleCode] = $handler;
    }

    /**
     * Vrati handler prispely pro dany modul
     */
    public function handlerFor(string $moduleCode): ?PublicCmsRouteHandlerInterface
    {
        $handler = $this->handlers[$moduleCode] ?? null;
        if ($handler instanceof Closure) {
            $handler = $handler();
            $this->handlers[$moduleCode] = $handler;
        }

        return $handler;
    }
}
