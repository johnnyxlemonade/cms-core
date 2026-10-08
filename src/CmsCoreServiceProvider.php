<?php

declare(strict_types=1);

namespace Lemonade\Cms;

use Lemonade\Cms\Http\Controller\PublicCmsRouteController;
use Lemonade\Cms\Migrations\CreateCoreCmsRoutes;
use Lemonade\Cms\Routing\Cms\CmsRouteCandidateResolver;
use Lemonade\Cms\Routing\Cms\CmsRouteRepository;
use Lemonade\Cms\Routing\Cms\CmsRouteRepositoryInterface;
use Lemonade\Cms\Routing\Cms\CmsRouteReservationService;
use Lemonade\Cms\Routing\Cms\PublicCmsCollectionHandlerRegistry;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteHandlerRegistry;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteRegistrar;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteResolver;
use Lemonade\Cms\Routing\Cms\PublicCmsUrlBuilder;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolver;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Registruje reusable CMS routing bez host adapteru
 */
final class CmsCoreServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje CMS runtime, migraci a verejny fallback route registrar
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(CmsRouteRepository::class, CmsRouteRepository::class);
        $container->singleton(CmsRouteCandidateResolver::class, CmsRouteCandidateResolver::class);
        $container->singleton(CmsRouteReservationService::class, CmsRouteReservationService::class);
        $container->singleton(
            CmsRouteRepositoryInterface::class,
            static fn(ContainerInterface $container): CmsRouteRepository => $container->get(CmsRouteRepository::class),
        );
        $container->scoped(PublicLocaleResolver::class, PublicLocaleResolver::class);
        $container->scoped(
            PublicLocaleResolution::class,
            static fn(ContainerInterface $container): PublicLocaleResolution => $container->get(PublicLocaleResolver::class)->resolve(),
        );
        $container->singleton(PublicCmsUrlBuilder::class, PublicCmsUrlBuilder::class);
        $container->singleton(PublicCmsRouteHandlerRegistry::class, PublicCmsRouteHandlerRegistry::class);
        $container->singleton(PublicCmsCollectionHandlerRegistry::class, PublicCmsCollectionHandlerRegistry::class);
        $container->scoped(PublicCmsRouteResolver::class, PublicCmsRouteResolver::class);
        $container->scoped(PublicCmsRouteController::class, PublicCmsRouteController::class);
        $container->singletonTagged(PublicCmsRouteRegistrar::class, PublicCmsRouteRegistrar::class, RouteRegistrarInterface::class);
        if ($container->isBound(MigrationRegistry::class)) {
            $container->get(MigrationRegistry::class)->register(CreateCoreCmsRoutes::class);
        }
    }
}
