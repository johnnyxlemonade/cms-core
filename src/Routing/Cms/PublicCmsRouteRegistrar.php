<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Http\Controller\PublicCmsRouteController;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Registers the public CMS fallback route
 */
final readonly class PublicCmsRouteRegistrar implements RouteRegistrarInterface
{
    /**
     * Returns the stable identifier of the fallback registrar
     */
    public function id(): string
    {
        return 'core.cms.public-fallback';
    }

    /**
     * Places the fallback after concrete routes
     */
    public function priority(): int
    {
        return 10000;
    }

    /**
     * Registers the module-neutral fallback for public CMS paths
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
