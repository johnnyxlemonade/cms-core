<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Http\Controller\PublicCmsAttachmentDownloadController;
use Lemonade\Cms\Routing\Cms\PublicCmsApiEndpointProvider;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistrar;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Overuje attachment route zaregistrovanou pres Framework API registry
 */
final class PublicCmsApiEndpointProviderTest extends TestCase
{
    /**
     * Overuje public policy, puvodni URL a file parametr canonical RouteMatch
     */
    public function testItRegistersPublicParameterizedAttachmentEndpoint(): void
    {
        $endpoints = new ApiEndpointRegistry();
        (new PublicCmsApiEndpointProvider())->register($endpoints);

        $endpoint = $endpoints->findByName('api.file.download');

        self::assertNotNull($endpoint);
        self::assertSame(ApiAccess::Public, $endpoint->access());
        self::assertSame('/file/{file}/download', $endpoint->path());
        self::assertSame(PublicCmsAttachmentDownloadController::class, $endpoint->controllerAction()->controllerClass());

        $router = new Router();
        (new ApiEndpointRegistrar($router, $endpoints))->registerRoutes('/api');
        $match = $router->match(new ServerRequest('GET', '/api/file/14/download'));

        self::assertSame('api.file.download', $match->name());
        self::assertSame('/api/file/{file}/download', $match->path());
        self::assertSame('GET', $match->method());
        self::assertSame(['file' => '14'], $match->params());
    }
}
