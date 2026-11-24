<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Locale\PublicLocaleRegistryInterface;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolver;
use Lemonade\Cms\Routing\Locale\PublicLocaleRoutingMiddleware;
use Lemonade\Cms\Routing\Locale\PublicLocaleSnapshot;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verifies lazy public locale normalization and installer bypass
 */
final class PublicLocaleRoutingMiddlewareTest extends TestCase
{
    /**
     * Verifies normalized dispatch paths for enabled nondefault locales
     */
    public function testEnabledNonDefaultLocaleNormalizesOnlyTheDispatchPath(): void
    {
        $handler = new CapturingLocaleRequestHandler();
        $request = new ServerRequest('GET', '/en/contact?source=menu');

        $this->middleware(new PublicLocaleTestRegistry(), $request)->process($request, $handler);

        $dispatchedRequest = $handler->request;
        self::assertNotNull($dispatchedRequest);
        self::assertSame('/en/contact', $dispatchedRequest->getUri()->getPath());
        self::assertSame('source=menu', $dispatchedRequest->getUri()->getQuery());
        self::assertSame('/contact', $dispatchedRequest->getAttribute(RouteRequestAttributes::DISPATCH_PATH));
    }

    /**
     * Verifies routing of the root for an enabled nondefault locale
     */
    public function testEnabledNonDefaultLocaleRootDispatchesToTheHostRoot(): void
    {
        $handler = new CapturingLocaleRequestHandler();
        $request = new ServerRequest('GET', '/en');

        $this->middleware(new PublicLocaleTestRegistry(), $request)->process($request, $handler);

        $dispatchedRequest = $handler->request;
        self::assertNotNull($dispatchedRequest);
        self::assertSame('/en', $dispatchedRequest->getUri()->getPath());
        self::assertSame('/', $dispatchedRequest->getAttribute(RouteRequestAttributes::DISPATCH_PATH));
    }

    /**
     * Verifies default locale and ordinary dispatch paths remain intact
     */
    public function testDefaultLocaleAndUnknownPathKeepTheirOriginalDispatchPath(): void
    {
        foreach (['/kontakt', '/xx'] as $path) {
            $handler = new CapturingLocaleRequestHandler();
            $request = new ServerRequest('GET', $path);

            $this->middleware(new PublicLocaleTestRegistry(), $request)->process($request, $handler);

            $dispatchedRequest = $handler->request;
            self::assertNotNull($dispatchedRequest);
            self::assertNull($dispatchedRequest->getAttribute(RouteRequestAttributes::DISPATCH_PATH));
        }
    }

    /**
     * Verifies canonical redirects from default locale prefixes with queries
     */
    public function testDefaultLocalePrefixRedirectsBeforeRoutingAndPreservesQuery(): void
    {
        $handler = new CapturingLocaleRequestHandler();
        $request = new ServerRequest('GET', '/cs/contact?source=menu');

        $response = $this->middleware(new PublicLocaleTestRegistry(), $request)->process($request, $handler);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/contact?source=menu', $response->getHeaderLine('Location'));
        self::assertNull($handler->request);
    }

    /**
     * Verifies known disabled locales stop before routing
     */
    public function testDisabledKnownLocaleStopsBeforeRouting(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = new ServerRequest('GET', '/de/contact');
        $this->middleware(new PublicLocaleTestRegistry(), $request)->process($request, new CapturingLocaleRequestHandler());
    }

    /**
     * Verifies installer bypass avoids locale registry and database reads
     */
    public function testInstallerBypassDoesNotResolveThePublicLocaleRegistry(): void
    {
        $registry = new PublicLocaleTestRegistry();
        $request = (new ServerRequest('GET', '/admin/install'))
            ->withAttribute(PublicLocaleRoutingMiddleware::BYPASS_ATTRIBUTE, true);
        $handler = new CapturingLocaleRequestHandler();

        $this->middleware($registry, $request)->process($request, $handler);

        self::assertSame(0, $registry->snapshotReads());
        self::assertSame($request, $handler->request);
    }

    /**
     * Verifies technical API paths have no locale dispatch variant
     */
    public function testApiPathBypassesThePublicLocaleRegistry(): void
    {
        $registry = new PublicLocaleTestRegistry();
        $request = new ServerRequest('GET', '/api/file/download/14');
        $handler = new CapturingLocaleRequestHandler();

        $this->middleware($registry, $request)->process($request, $handler);

        self::assertSame(0, $registry->snapshotReads());
        self::assertSame($request, $handler->request);
    }

    /**
     * Verifies locale prefixes do not expose technical API paths
     */
    public function testLocalePrefixedApiPathReturnsNotFound(): void
    {
        foreach (['/en/api/file/download/14', '/cs/api/file/download/14'] as $path) {
            $registry = new PublicLocaleTestRegistry();
            $request = new ServerRequest('GET', $path);
            $handler = new CapturingLocaleRequestHandler();

            $response = $this->middleware($registry, $request)->process($request, $handler);

            self::assertSame(404, $response->getStatusCode());
            self::assertSame(1, $registry->snapshotReads());
            self::assertNull($handler->request);
        }
    }

    /**
     * Creates middleware with a request scoped lazy resolver
     */
    private function middleware(PublicLocaleTestRegistry $registry, ServerRequestInterface $request): PublicLocaleRoutingMiddleware
    {
        return new PublicLocaleRoutingMiddleware(
            new PublicLocaleResolver($registry, $request),
            new Psr17Factory(),
        );
    }
}

/**
 * Provides a stable public locale snapshot and tracks reads
 */
final class PublicLocaleTestRegistry implements PublicLocaleRegistryInterface
{
    private int $snapshotReads = 0;

    /**
     * Returns an atomic snapshot without a database
     */
    public function snapshot(): PublicLocaleSnapshot
    {
        ++$this->snapshotReads;

        return PublicLocaleSnapshot::fromRows([
            ['code' => 'cs', 'enabled' => 1, 'is_default' => 1],
            ['code' => 'en', 'enabled' => 1, 'is_default' => 0],
            ['code' => 'de', 'enabled' => 0, 'is_default' => 0],
        ]);
    }

    /**
     * Returns the number of locale snapshot requests
     */
    public function snapshotReads(): int
    {
        return $this->snapshotReads;
    }
}

/**
 * Captures the request passed to the next handler
 */
final class CapturingLocaleRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    /**
     * Stores the request and returns an empty success response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return (new Psr17Factory())->createResponse();
    }
}
