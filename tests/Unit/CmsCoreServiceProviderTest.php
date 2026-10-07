<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit;

use Lemonade\Cms\CmsCoreServiceProvider;
use Lemonade\Cms\Http\Controller\PublicCmsRouteController;
use Lemonade\Cms\Routing\CmsRouteRepositoryInterface;
use Lemonade\Cms\Routing\CmsRouteReservationService;
use Lemonade\Cms\Routing\PublicCmsRouteRegistrar;
use Lemonade\Cms\Routing\PublicCmsRouteResolver;
use Lemonade\Cms\Routing\PublicLocaleResolver;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Overuje registraci reusable CMS runtime bez host adapteru
 */
final class CmsCoreServiceProviderTest extends TestCase
{
    /**
     * Overuje registraci CMS routingu bez locale a module adapteru
     */
    public function testItRegistersReusableCmsRuntimeWithoutHostPortBindings(): void
    {
        $container = new Container();

        (new CmsCoreServiceProvider())->register($container);

        self::assertTrue($container->isBound(CmsRouteRepositoryInterface::class));
        self::assertTrue($container->isBound(CmsRouteReservationService::class));
        self::assertTrue($container->isBound(PublicLocaleResolver::class));
        self::assertTrue($container->isBound(PublicCmsRouteResolver::class));
        self::assertTrue($container->isBound(PublicCmsRouteController::class));
        self::assertTrue($container->isBound(PublicCmsRouteRegistrar::class));
    }
}
