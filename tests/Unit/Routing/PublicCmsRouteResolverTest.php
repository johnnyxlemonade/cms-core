<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\CmsRoute;
use Lemonade\Cms\Routing\Cms\CmsRouteRepositoryInterface;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteHandlerInterface;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteHandlerRegistry;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteResolver;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolver;
use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\View\ViewRendererInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Verifies public CMS route resolution and request scoped handlers
 */
final class PublicCmsRouteResolverTest extends TestCase
{
    public function testCsWithoutPrefixUsesTheDefaultLocale(): void
    {
        $resolver = $this->localeResolver('/x');

        $resolution = $resolver->resolve();

        self::assertSame('cs', $resolution->getLocale());
        self::assertSame('x', $resolution->getPath());
    }

    public function testExplicitCsPrefixRedirectsToTheUnprefixedUrl(): void
    {
        $response = $this->resolver('/cs/foo')->resolve();

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/foo', $response->getHeaderLine('Location'));
    }

    public function testExplicitCsPrefixRedirectKeepsTheQueryString(): void
    {
        $response = $this->resolver('/cs/test?page=2&utm_source=mail')->resolve();

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/test?page=2&utm_source=mail', $response->getHeaderLine('Location'));
    }

    public function testNonDefaultEnabledLocaleIsSeparatedFromTheRoutePath(): void
    {
        $resolver = $this->localeResolver('/en/x');

        $resolution = $resolver->resolve();

        self::assertSame('en', $resolution->getLocale());
        self::assertSame('x', $resolution->getPath());
    }

    public function testUnknownPathReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/chybi')->resolve();
    }

    public function testDisabledLocaleReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/de/x')->resolve();
    }

    public function testRouteWithDisabledModuleReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'x')]);
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/x', routes: $routes, modules: new FakeModules([]))->resolve();
    }

    public function testRouteWithoutRegisteredHandlerReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'x')]);
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/x', routes: $routes)->resolve();
    }

    public function testValidRouteCallsItsHandlerWithEntityAndLocale(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'x')]);
        $handler = new FakeHandler();
        $handlers = new PublicCmsRouteHandlerRegistry();
        $handlers->register('news', $handler);

        $response = $this->resolver('/x', routes: $routes, handlers: $handlers)->resolve();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([42, 'cs', 'cs', 'x'], $handler->received);
    }

    /**
     * Verifies lazy handlers remain request scoped and uncached
     */
    public function testHandlerFactoryUsesTheActiveRequestScope(): void
    {
        $container = new Container();
        $container->scoped(ScopedFakeHandler::class, ScopedFakeHandler::class);
        $container->freeze();
        $handlers = new PublicCmsRouteHandlerRegistry();
        $handlers->registerFactory(
            'news',
            static fn(ContainerInterface $container): ScopedFakeHandler => $container->get(ScopedFakeHandler::class),
        );
        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);

        $firstHandler = $handlers->handlerFor('news', $firstScope);
        $sameRequestHandler = $handlers->handlerFor('news', $firstScope);
        $secondHandler = $handlers->handlerFor('news', $secondScope);

        self::assertInstanceOf(ScopedFakeHandler::class, $firstHandler);
        self::assertSame($firstHandler, $sameRequestHandler);
        self::assertNotSame($firstHandler, $secondHandler);

        $firstScope->close();
        $secondScope->close();
    }

    public function testCanonicalUrlBuilderOmitsOnlyTheDefaultLocale(): void
    {
        $builder = new \Lemonade\Cms\Routing\Cms\PublicCmsUrlBuilder();
        $resolution = PublicLocaleResolution::route('cs', 'cs', '');

        self::assertSame('/x', $builder->build($resolution, 'cs', 'x'));
        self::assertSame('/en/x', $builder->build($resolution, 'en', 'x'));
    }

    /**
     * Verifies request scoped resolution loads one locale snapshot
     */
    public function testMemoizesTheCurrentRequestResolution(): void
    {
        $locales = new FakeLocaleRegistry();
        $resolver = new PublicLocaleResolver($locales, new ServerRequest('GET', '/en/x'));

        self::assertSame($resolver->resolve(), $resolver->resolve());
        self::assertSame(1, $locales->snapshotCalls);
    }

    private function localeResolver(string $path): PublicLocaleResolver
    {
        return new PublicLocaleResolver(new FakeLocaleRegistry(), new ServerRequest('GET', $path));
    }

    private function resolver(
        string $path,
        ?CmsRouteRepositoryInterface $routes = null,
        ?PublicModuleStateResolverInterface $modules = null,
        ?PublicCmsRouteHandlerRegistry $handlers = null,
    ): PublicCmsRouteResolver {
        return new PublicCmsRouteResolver(
            $this->localeResolver($path)->resolve(),
            $routes ?? new FakeRoutes(),
            $modules ?? new FakeModules(['news']),
            $handlers ?? new PublicCmsRouteHandlerRegistry(),
            new Psr17Factory(),
            new FakeViews(),
            new ServerRequest('GET', $path),
            new Container(),
        );
    }
}

/**
 * Provides a fixed public locale snapshot for route tests
 */
final class FakeLocaleRegistry implements PublicLocaleRegistryInterface
{
    public int $snapshotCalls = 0;

    public function snapshot(): PublicLocaleSnapshot
    {
        ++$this->snapshotCalls;

        return PublicLocaleSnapshot::fromRows([
            ['code' => 'cs', 'enabled' => 1, 'is_default' => 1],
            ['code' => 'en', 'enabled' => 1, 'is_default' => 0],
            ['code' => 'de', 'enabled' => 0, 'is_default' => 0],
        ]);
    }
}

/**
 * Finds predefined CMS routes for resolver tests
 */
final class FakeRoutes implements CmsRouteRepositoryInterface
{
    /**
     * Routes indexed by locale and canonical path
     *
     * @var array<string, CmsRoute>
     */
    private array $routes = [];

    /**
     * Receives predefined canonical routes
     *
     * @param list<CmsRoute> $routes
     */
    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            $this->routes[$route->getLocale() . ':' . $route->getPath()] = $route;
        }
    }

    public function find(string $locale, string $path): ?CmsRoute
    {
        return $this->routes[$locale . ':' . $path] ?? null;
    }
}

/**
 * Reports a fixed set of enabled CMS modules
 */
final class FakeModules implements PublicModuleStateResolverInterface
{
    /**
     * Receives enabled module codes
     *
     * @param list<string> $enabled
     */
    public function __construct(private readonly array $enabled) {}

    public function isDiscoveredInstalledAndEnabled(string $moduleCode): bool
    {
        return in_array($moduleCode, $this->enabled, true);
    }
}

/**
 * Records the resolved public route passed to a handler
 */
final class FakeHandler implements PublicCmsRouteHandlerInterface
{
    /**
     * Last route passed to the handler
     *
     * @var array{int, string, string, string}|null
     */
    public ?array $received = null;

    public function handle(
        int $entityId,
        PublicLocaleResolution $resolution,
        CmsRoute $route,
        ViewRendererInterface $views,
    ): ResponseInterface {
        $this->received = [$entityId, $resolution->getDefaultLocale(), (string) $resolution->getLocale(), $route->getPath()];

        return (new Psr17Factory())->createResponse(200);
    }
}

/**
 * Records creation of a request scoped test handler
 */
final class ScopedFakeHandler implements PublicCmsRouteHandlerInterface
{
    public function handle(
        int $entityId,
        PublicLocaleResolution $resolution,
        CmsRoute $route,
        ViewRendererInterface $views,
    ): ResponseInterface {
        return (new Psr17Factory())->createResponse(200);
    }
}

/**
 * Provides a minimal view renderer for route tests
 */
final class FakeViews implements ViewRendererInterface
{
    public function render(string $template, array $data = [], int $status = 200): ResponseInterface
    {
        return (new Psr17Factory())->createResponse($status);
    }

    public function content(string $template, array $data = []): string
    {
        return '';
    }
}
