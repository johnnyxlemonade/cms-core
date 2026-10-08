<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\CmsRoute;
use Lemonade\Cms\Routing\Cms\CmsRouteRepositoryInterface;
use Lemonade\Cms\Routing\Cms\PublicCmsCollectionHandlerRegistry;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteHandlerInterface;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteHandlerRegistry;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteResolver;
use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolver;
use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;
use Lemonade\Cms\Routing\Module\PublicModuleRoutePrefixRepositoryInterface;
use Lemonade\Cms\Routing\Module\PublicModuleStateResolverInterface;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\View\ViewRendererInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class PublicCmsRouteResolverTest extends TestCase
{
    public function testCsWithoutPrefixUsesTheDefaultLocale(): void
    {
        $resolver = $this->localeResolver('/aktuality/x');

        $resolution = $resolver->resolve();

        self::assertSame('cs', $resolution->locale());
        self::assertSame('aktuality/x', $resolution->path());
    }

    public function testExplicitCsPrefixRedirectsToTheUnprefixedUrl(): void
    {
        $response = $this->resolver('/cs/foo')->resolve();

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/foo', $response->getHeaderLine('Location'));
    }

    public function testExplicitCsPrefixRedirectKeepsTheQueryString(): void
    {
        $response = $this->resolver('/cs/aktuality/test?page=2&utm_source=mail')->resolve();

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/aktuality/test?page=2&utm_source=mail', $response->getHeaderLine('Location'));
    }

    public function testNonDefaultEnabledLocaleIsSeparatedFromTheRoutePath(): void
    {
        $resolver = $this->localeResolver('/en/news/x');

        $resolution = $resolver->resolve();

        self::assertSame('en', $resolution->locale());
        self::assertSame('news/x', $resolution->path());
    }

    public function testUnknownPathReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/aktuality/chybi')->resolve();
    }

    public function testDisabledLocaleReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/de/neuigkeiten/x')->resolve();
    }

    public function testRouteWithDisabledModuleReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/aktuality/x', routes: $routes, modules: new FakeModules([]))->resolve();
    }

    public function testRouteWithoutRegisteredHandlerReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/aktuality/x', routes: $routes)->resolve();
    }

    public function testRouteWithOutdatedDatabasePrefixReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $handlers = new PublicCmsRouteHandlerRegistry();
        $handlers->register('news', new FakeHandler());
        $this->expectException(NotFoundHttpException::class);

        $this->resolver('/aktuality/x', routes: $routes, prefixes: new FakePrefixes(['news:cs' => 'novinky']), handlers: $handlers)->resolve();
    }

    public function testValidRouteCallsItsHandlerWithEntityAndLocale(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $handler = new FakeHandler();
        $handlers = new PublicCmsRouteHandlerRegistry();
        $handlers->register('news', $handler);

        $response = $this->resolver('/aktuality/x', routes: $routes, handlers: $handlers)->resolve();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([42, 'cs', 'cs', 'aktuality/x'], $handler->received);
    }

    public function testCanonicalUrlBuilderOmitsOnlyTheDefaultLocale(): void
    {
        $builder = new \Lemonade\Cms\Routing\Cms\PublicCmsUrlBuilder();
        $resolution = PublicLocaleResolution::route('cs', 'cs', '');

        self::assertSame('/aktuality/x', $builder->build($resolution, 'cs', 'aktuality/x'));
        self::assertSame('/en/news/x', $builder->build($resolution, 'en', 'news/x'));
    }

    /**
     * Overuje, ze request-scoped resolver nacte snapshot jen jednou
     */
    public function testMemoizesTheCurrentRequestResolution(): void
    {
        $locales = new FakeLocaleRegistry();
        $resolver = new PublicLocaleResolver($locales, new ServerRequest('GET', '/en/news/x'));

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
        ?PublicModuleRoutePrefixRepositoryInterface $prefixes = null,
        ?PublicCmsRouteHandlerRegistry $handlers = null,
    ): PublicCmsRouteResolver {
        return new PublicCmsRouteResolver(
            $this->localeResolver($path)->resolve(),
            $routes ?? new FakeRoutes(),
            $modules ?? new FakeModules(['news']),
            $prefixes ?? new FakePrefixes(['news:cs' => 'aktuality', 'news:en' => 'news']),
            $handlers ?? new PublicCmsRouteHandlerRegistry(),
            new PublicCmsCollectionHandlerRegistry(),
            new Psr17Factory(),
            new FakeViews(),
            new Container(),
            new ServerRequest('GET', $path),
        );
    }
}

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

final class FakeRoutes implements CmsRouteRepositoryInterface
{
    /** @var array<string, CmsRoute> */
    private array $routes = [];

    /** @param list<CmsRoute> $routes */
    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            $this->routes[$route->locale() . ':' . $route->path()] = $route;
        }
    }

    public function find(string $locale, string $path): ?CmsRoute
    {
        return $this->routes[$locale . ':' . $path] ?? null;
    }
}

final class FakeModules implements PublicModuleStateResolverInterface
{
    /** @param list<string> $enabled */
    public function __construct(private readonly array $enabled) {}

    public function isDiscoveredInstalledAndEnabled(string $moduleCode): bool
    {
        return in_array($moduleCode, $this->enabled, true);
    }
}

final class FakePrefixes implements PublicModuleRoutePrefixRepositoryInterface
{
    /** @param array<string, string> $prefixes */
    public function __construct(private readonly array $prefixes) {}

    public function prefixFor(string $moduleCode, string $locale): ?string
    {
        return $this->prefixes[$moduleCode . ':' . $locale] ?? null;
    }

    public function moduleFor(string $locale, string $prefix): ?string
    {
        foreach ($this->prefixes as $key => $value) {
            [$moduleCode, $prefixLocale] = explode(':', $key, 2);
            if ($prefixLocale === $locale && $value === $prefix) {
                return $moduleCode;
            }
        }

        return null;
    }
}

final class FakeHandler implements PublicCmsRouteHandlerInterface
{
    /** @var array{int, string, string, string}|null */
    public ?array $received = null;

    public function handle(
        int $entityId,
        PublicLocaleResolution $resolution,
        CmsRoute $route,
        ViewRendererInterface $views,
    ): ResponseInterface {
        $this->received = [$entityId, $resolution->defaultLocale(), (string) $resolution->locale(), $route->path()];

        return (new Psr17Factory())->createResponse(200);
    }
}

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
