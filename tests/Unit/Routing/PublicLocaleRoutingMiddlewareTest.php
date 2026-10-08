<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Cms\Routing\Locale\PublicLocaleRoutingMiddleware;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PublicLocaleRoutingMiddlewareTest extends TestCase
{
    public function testEnabledNonDefaultLocaleNormalizesOnlyTheDispatchPath(): void
    {
        $handler = new CapturingLocaleRequestHandler();
        $request = new ServerRequest('GET', '/en/contact?source=menu');

        $this->middleware(PublicLocaleResolution::route('cs', 'en', 'contact'))->process($request, $handler);

        $dispatchedRequest = $handler->request;
        self::assertNotNull($dispatchedRequest);
        self::assertSame('/en/contact', $dispatchedRequest->getUri()->getPath());
        self::assertSame('source=menu', $dispatchedRequest->getUri()->getQuery());
        self::assertSame('/contact', $dispatchedRequest->getAttribute(RouteRequestAttributes::DISPATCH_PATH));
    }

    public function testEnabledNonDefaultLocaleRootDispatchesToTheHostRoot(): void
    {
        $handler = new CapturingLocaleRequestHandler();

        $this->middleware(PublicLocaleResolution::route('cs', 'en', ''))->process(new ServerRequest('GET', '/en'), $handler);

        $dispatchedRequest = $handler->request;
        self::assertNotNull($dispatchedRequest);
        self::assertSame('/en', $dispatchedRequest->getUri()->getPath());
        self::assertSame('/', $dispatchedRequest->getAttribute(RouteRequestAttributes::DISPATCH_PATH));
    }

    public function testDefaultLocaleAndUnknownPathKeepTheirOriginalDispatchPath(): void
    {
        foreach ([
            PublicLocaleResolution::route('cs', 'cs', 'kontakt'),
            PublicLocaleResolution::route('cs', 'cs', 'xx'),
        ] as $resolution) {
            $handler = new CapturingLocaleRequestHandler();

            $this->middleware($resolution)->process(new ServerRequest('GET', '/' . $resolution->path()), $handler);

            $dispatchedRequest = $handler->request;
            self::assertNotNull($dispatchedRequest);
            self::assertNull($dispatchedRequest->getAttribute(RouteRequestAttributes::DISPATCH_PATH));
        }
    }

    public function testDefaultLocalePrefixRedirectsBeforeRoutingAndPreservesQuery(): void
    {
        $handler = new CapturingLocaleRequestHandler();
        $response = $this->middleware(PublicLocaleResolution::redirect('cs', '/contact'))
            ->process(new ServerRequest('GET', '/cs/contact?source=menu'), $handler);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/contact?source=menu', $response->getHeaderLine('Location'));
        self::assertNull($handler->request);
    }

    public function testDisabledKnownLocaleStopsBeforeRouting(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->middleware(PublicLocaleResolution::notFound('cs'))
            ->process(new ServerRequest('GET', '/de/contact'), new CapturingLocaleRequestHandler());
    }

    private function middleware(PublicLocaleResolution $resolution): PublicLocaleRoutingMiddleware
    {
        return new PublicLocaleRoutingMiddleware($resolution, new Psr17Factory());
    }
}

final class CapturingLocaleRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return (new Psr17Factory())->createResponse();
    }
}
