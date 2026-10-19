<?php

declare(strict_types=1);

namespace Lemonade\Cms;

use Lemonade\Cms\Http\Controller\PublicCmsAttachmentDownloadController;
use Lemonade\Cms\Http\Controller\PublicCmsRouteController;
use Lemonade\Cms\Migrations\CreateCoreCmsRoutes;
use Lemonade\Cms\Routing\Cms\CmsRouteRepository;
use Lemonade\Cms\Routing\Cms\CmsRouteRepositoryInterface;
use Lemonade\Cms\Routing\Cms\PublicCmsAttachmentDownloadHandlerRegistry;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteHandlerRegistry;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteRegistrar;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteResolver;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolver;
use Lemonade\Cms\Routing\Locale\PublicLocaleRoutingMiddleware;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Registruje reusable CMS routing bez host adapteru
 */
final class CmsCoreServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zapisuje CMS runtime, migraci a verejny fallback route registrar
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->transient(CmsRouteRepositoryInterface::class, CmsRouteRepository::class);
        $container->scoped(PublicLocaleResolver::class, PublicLocaleResolver::class);
        $container->scoped(
            PublicLocaleResolution::class,
            static fn(ContainerInterface $container): PublicLocaleResolution => $container->get(PublicLocaleResolver::class)->resolve(),
        );
        $container->scoped(PublicLocaleRoutingMiddleware::class, PublicLocaleRoutingMiddleware::class);
        $container->singleton(PublicCmsRouteHandlerRegistry::class, PublicCmsRouteHandlerRegistry::class);
        $container->singleton(PublicCmsAttachmentDownloadHandlerRegistry::class, PublicCmsAttachmentDownloadHandlerRegistry::class);
        $container->scoped(PublicCmsRouteResolver::class, PublicCmsRouteResolver::class);
        $container->scoped(PublicCmsRouteController::class, PublicCmsRouteController::class);
        $container->scoped(PublicCmsAttachmentDownloadController::class, PublicCmsAttachmentDownloadController::class);
        $container->transient(PublicCmsRouteRegistrar::class, PublicCmsRouteRegistrar::class);
        $container->tag(PublicCmsRouteRegistrar::class, RouteRegistrarInterface::class);
    }

    /**
     * Pripojuje CMS middleware a core migraci po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        if (method_exists($container, 'isBound') && $container->isBound(MiddlewareStack::class)) {
            $container->get(MiddlewareStack::class)->prepend(PublicLocaleRoutingMiddleware::class);
        }

        if (method_exists($container, 'isBound') && $container->isBound(MigrationRegistry::class)) {
            $container->get(MigrationRegistry::class)->register(CreateCoreCmsRoutes::class);
        }
    }
}
