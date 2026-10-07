<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

use Lemonade\Cms\Http\Controller\PublicCmsRouteController;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Registruje zachytnou verejnou CMS routu
 */
final class PublicCmsRouteRegistrar implements RouteRegistrarInterface
{
    /**
     * Vrati stabilni identifikator fallback registraru
     */
    public function id(): string
    {
        return 'core.cms.public-fallback';
    }

    /**
     * Urcuje, ze fallback nasleduje az po konkretnich routach
     */
    public function priority(): int
    {
        return 10000;
    }

    /**
     * Registruje package-neutralni fallback pro verejne CMS cesty
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'frontend.cms-route',
            path: '/{path:any}',
            action: ControllerAction::for(
                controllerClass: PublicCmsRouteController::class,
                method: 'show',
            ),
        );
    }
}
