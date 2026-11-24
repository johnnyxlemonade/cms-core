<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\CmsReservedPathGuard;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Verifies protection of system and host routes
 */
final class CmsReservedPathGuardTest extends TestCase
{
    /**
     * Distinguishes an exact first segment from a similar word
     */
    public function testProtectsSystemSegmentsExactly(): void
    {
        $guard = $this->guard('/backoffice');

        self::assertTrue($guard->isReserved('api'));
        self::assertTrue($guard->isReserved('api/files'));
        self::assertTrue($guard->isReserved('assets'));
        self::assertTrue($guard->isReserved('assets/site.css'));
        self::assertTrue($guard->isReserved('static/app.js'));
        self::assertTrue($guard->isReserved('en/news'));
        self::assertFalse($guard->isReserved('aplikace'));
        self::assertFalse($guard->isReserved('assets-old'));
        self::assertFalse($guard->isReserved('english'));
    }

    /**
     * Uses the host router for Admin base paths and frontend routes
     */
    public function testProtectsRoutesOwnedByHost(): void
    {
        $guard = $this->guard('/backoffice');

        self::assertTrue($guard->isReserved(''));
        self::assertTrue($guard->isReserved('backoffice'));
        self::assertTrue($guard->isReserved('backoffice/arbitrary-child'));
        self::assertTrue($guard->isReserved('news'));
        self::assertFalse($guard->isReserved('novinky'));
    }

    private function guard(string $adminBasePath): CmsReservedPathGuard
    {
        $router = new Router();
        $router->getNamed('frontend.home', '/', ControllerAction::for(self::class, 'endpoint'));
        $router->getNamed('frontend.news', '/news', ControllerAction::for(self::class, 'endpoint'));
        $router->group($adminBasePath, static function (Router $router): void {
            $router->getNamed('admin.dashboard', '/', ControllerAction::for(self::class, 'endpoint'));
            $router->getNamed('admin.fallback', '/{path:any}', ControllerAction::for(self::class, 'endpoint'));
        });
        $router->getNamed(
            'frontend.cms-route',
            '/{path:any}',
            ControllerAction::for(self::class, 'endpoint'),
        );

        return new CmsReservedPathGuard($router, new ServerRequest('GET', '/'), new ReservedPathLocaleRegistry());
    }

    public function endpoint(): void {}
}

/**
 * Provides a stable locale snapshot for reserved path tests
 */
final readonly class ReservedPathLocaleRegistry implements PublicLocaleRegistryInterface
{
    /**
     * Returns default Czech and known English locales
     */
    public function snapshot(): PublicLocaleSnapshot
    {
        return PublicLocaleSnapshot::fromRows([
            ['code' => 'cs', 'enabled' => 1, 'is_default' => 1],
            ['code' => 'en', 'enabled' => 1, 'is_default' => 0],
        ]);
    }
}
