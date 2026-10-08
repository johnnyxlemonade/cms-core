<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\PublicCmsRouteRegistrar;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class PublicCmsRouteRegistrarTest extends TestCase
{
    public function testItRegistersTheCmsFallbackAfterConcreteRoutes(): void
    {
        $router = new Router();
        $router->getNamed('frontend.home', '/', ControllerAction::for('Example\\Controllers\\HomeController', 'index'));
        $router->getNamed('api.health', '/api/health', ControllerAction::for('Example\\Controllers\\HealthController', 'index'));
        $router->getNamed('admin.module', '/admin/{module}', ControllerAction::for('Example\\Controllers\\AdminController', 'index'));

        (new PublicCmsRouteRegistrar())->registerRoutes($router);

        self::assertSame('Example\\Controllers\\HomeController', $router->match(new ServerRequest('GET', '/'))->controller());
        self::assertSame('Example\\Controllers\\HealthController', $router->match(new ServerRequest('GET', '/api/health'))->controller());
        self::assertSame('Example\\Controllers\\AdminController', $router->match(new ServerRequest('GET', '/admin/news'))->controller());
        self::assertSame('Lemonade\\Cms\\Http\\Controller\\PublicCmsRouteController', $router->match(new ServerRequest('GET', '/aktuality/test'))->controller());
        self::assertSame(10000, (new PublicCmsRouteRegistrar())->priority());
    }
}
