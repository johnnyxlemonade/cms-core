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
 * Overuje lazy public locale normalizaci a installer bypass
 */
final class PublicLocaleRoutingMiddlewareTest extends TestCase
{
    /**
     * Overuje normalizaci dispatch cesty aktivni non-default lokalizace
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
     * Overuje predani korene aktivni non-default lokalizace host routingu
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
     * Overuje zachovani dispatch cesty pro defaultni lokalizaci a bezny path
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
     * Overuje canonical redirect defaultniho locale prefixu vcetne query
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
     * Overuje zastaveni disabled znamou lokalizaci pred routingem
     */
    public function testDisabledKnownLocaleStopsBeforeRouting(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = new ServerRequest('GET', '/de/contact');
        $this->middleware(new PublicLocaleTestRegistry(), $request)->process($request, new CapturingLocaleRequestHandler());
    }

    /**
     * Overuje, ze installer bypass nespousti locale registry ani jeho databazovy snapshot
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
     * Vytvari middleware s request-scoped lazy resolverem
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
 * Poskytuje stabilni public locale snapshot a eviduje jeho cteni
 */
final class PublicLocaleTestRegistry implements PublicLocaleRegistryInterface
{
    private int $snapshotReads = 0;

    /**
     * Vraci atomicky snapshot bez zavislosti na databazi
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
     * Vraci pocet pozadavku na locale snapshot
     */
    public function snapshotReads(): int
    {
        return $this->snapshotReads;
    }
}

/**
 * Zachycuje request predany dalsimu middleware nebo dispatchi
 */
final class CapturingLocaleRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    /**
     * Uklada request a vraci prazdnou uspesnou odpoved
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return (new Psr17Factory())->createResponse();
    }
}
