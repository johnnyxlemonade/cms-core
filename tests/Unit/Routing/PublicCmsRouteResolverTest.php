<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\CmsRoute;
use Lemonade\Cms\Routing\CmsRouteRepositoryInterface;
use Lemonade\Cms\Routing\PublicCmsCollectionHandlerRegistry;
use Lemonade\Cms\Routing\PublicCmsRouteHandlerInterface;
use Lemonade\Cms\Routing\PublicCmsRouteHandlerRegistry;
use Lemonade\Cms\Routing\PublicCmsRouteResolver;
use Lemonade\Cms\Routing\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\PublicLocaleResolver;
use Lemonade\Cms\Routing\PublicModuleRoutePrefixRepositoryInterface;
use Lemonade\Cms\Routing\PublicModuleStateResolverInterface;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\View\ViewRendererInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class PublicCmsRouteResolverTest extends TestCase
{
    public function testCsWithoutPrefixUsesTheDefaultLocale(): void
    {
        $resolver = new PublicLocaleResolver(new FakeLocaleRegistry());

        $resolution = $resolver->resolve('/aktuality/x');

        self::assertSame('cs', $resolution->locale());
        self::assertSame('aktuality/x', $resolution->path());
    }

    public function testExplicitCsPrefixRedirectsToTheUnprefixedUrl(): void
    {
        $response = $this->resolver()->resolve('/cs/foo');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/foo', $response->getHeaderLine('Location'));
    }

    public function testExplicitCsPrefixRedirectKeepsTheQueryString(): void
    {
        $response = $this->resolver()->resolve('/cs/aktuality/test', 'page=2&utm_source=mail');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/aktuality/test?page=2&utm_source=mail', $response->getHeaderLine('Location'));
    }

    public function testNonDefaultEnabledLocaleIsSeparatedFromTheRoutePath(): void
    {
        $resolver = new PublicLocaleResolver(new FakeLocaleRegistry());

        $resolution = $resolver->resolve('/en/news/x');

        self::assertSame('en', $resolution->locale());
        self::assertSame('news/x', $resolution->path());
    }

    public function testUnknownPathReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver()->resolve('/aktuality/chybi');
    }

    public function testDisabledLocaleReturnsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->resolver()->resolve('/de/neuigkeiten/x');
    }

    public function testRouteWithDisabledModuleReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $this->expectException(NotFoundHttpException::class);

        $this->resolver(routes: $routes, modules: new FakeModules([]))->resolve('/aktuality/x');
    }

    public function testRouteWithoutRegisteredHandlerReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $this->expectException(NotFoundHttpException::class);

        $this->resolver(routes: $routes)->resolve('/aktuality/x');
    }

    public function testRouteWithOutdatedDatabasePrefixReturnsNotFound(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $handlers = new PublicCmsRouteHandlerRegistry();
        $handlers->register('news', new FakeHandler());
        $this->expectException(NotFoundHttpException::class);

        $this->resolver(routes: $routes, prefixes: new FakePrefixes(['news:cs' => 'novinky']), handlers: $handlers)->resolve('/aktuality/x');
    }

    public function testValidRouteCallsItsHandlerWithEntityAndLocale(): void
    {
        $routes = new FakeRoutes([new CmsRoute(1, 'news', 42, 'cs', 'aktuality/x')]);
        $handler = new FakeHandler();
        $handlers = new PublicCmsRouteHandlerRegistry();
        $handlers->register('news', $handler);

        $response = $this->resolver(routes: $routes, handlers: $handlers)->resolve('/aktuality/x');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([42, 'cs', 'aktuality/x'], $handler->received);
    }

    public function testCanonicalUrlBuilderOmitsOnlyTheDefaultLocale(): void
    {
        $builder = new \Lemonade\Cms\Routing\PublicCmsUrlBuilder(new FakeLocaleRegistry());

        self::assertSame('/aktuality/x', $builder->build('cs', 'aktuality/x'));
        self::assertSame('/en/news/x', $builder->build('en', 'news/x'));
    }

    private function resolver(
        ?CmsRouteRepositoryInterface $routes = null,
        ?PublicModuleStateResolverInterface $modules = null,
        ?PublicModuleRoutePrefixRepositoryInterface $prefixes = null,
        ?PublicCmsRouteHandlerRegistry $handlers = null,
    ): PublicCmsRouteResolver {
        return new PublicCmsRouteResolver(
            new PublicLocaleResolver(new FakeLocaleRegistry()),
            $routes ?? new FakeRoutes(),
            $modules ?? new FakeModules(['news']),
            $prefixes ?? new FakePrefixes(['news:cs' => 'aktuality', 'news:en' => 'news']),
            $handlers ?? new PublicCmsRouteHandlerRegistry(),
            new PublicCmsCollectionHandlerRegistry(),
            new Psr17Factory(),
            new FakeViews(),
        );
    }
}

final class FakeLocaleRegistry implements PublicLocaleRegistryInterface
{
    public function defaultLocale(): string
    {
        return 'cs';
    }

    public function isEnabledNonDefault(string $locale): bool
    {
        return $locale === 'en';
    }

    public function isKnownLocale(string $locale): bool
    {
        return in_array($locale, ['cs', 'en', 'de'], true);
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
    /** @var array{int, string, string}|null */
    public ?array $received = null;

    public function handle(int $entityId, string $locale, CmsRoute $route, ViewRendererInterface $views): ResponseInterface
    {
        $this->received = [$entityId, $locale, $route->path()];

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
