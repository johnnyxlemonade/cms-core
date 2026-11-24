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
 * Verifies the attachment route registered through the Framework API registry
 */
final class PublicCmsApiEndpointProviderTest extends TestCase
{
    /**
     * Verifies public access, the download URL, and the fileId route parameter
     */
    public function testItRegistersPublicParameterizedAttachmentEndpoint(): void
    {
        $endpoints = new ApiEndpointRegistry();
        (new PublicCmsApiEndpointProvider())->register($endpoints);

        $endpoint = $endpoints->findByName('api.file.download');

        self::assertNotNull($endpoint);
        self::assertSame(ApiAccess::Public, $endpoint->access());
        self::assertSame('/file/download/{fileId}', $endpoint->path());
        self::assertSame(PublicCmsAttachmentDownloadController::class, $endpoint->controllerAction()->controllerClass());

        $router = new Router();
        (new ApiEndpointRegistrar($router, $endpoints))->registerRoutes('/api');
        $match = $router->match(new ServerRequest('GET', '/api/file/download/14'));

        self::assertSame('api.file.download', $match->name());
        self::assertSame('/api/file/download/{fileId}', $match->path());
        self::assertSame('GET', $match->method());
        self::assertSame(['fileId' => '14'], $match->params());
    }
}
