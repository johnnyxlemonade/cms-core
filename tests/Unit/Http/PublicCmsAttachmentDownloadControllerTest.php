<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Http;

use Lemonade\Cms\Http\Controller\PublicCmsAttachmentDownloadController;
use Lemonade\Cms\Routing\Cms\PublicCmsAttachmentDownloadHandlerInterface;
use Lemonade\Cms\Routing\Cms\PublicCmsAttachmentDownloadHandlerRegistry;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Http\Response\Responses;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Overuje zachovani hostem rizeneho verejneho attachment downloadu
 */
final class PublicCmsAttachmentDownloadControllerTest extends TestCase
{
    /**
     * Overuje predani file identity registrovanemu host handleru
     */
    public function testItDispatchesPublicAttachmentToConfiguredHostHandler(): void
    {
        $registry = new PublicCmsAttachmentDownloadHandlerRegistry();
        $registry->registerFactory(
            static fn(ContainerInterface $container): PublicCmsAttachmentDownloadHandlerInterface => new RecordingAttachmentDownloadHandler(),
        );

        $response = $this->controller($registry)->download(14);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('14', (string) $response->getBody());
    }

    /**
     * Overuje bezpecnou 404 pokud host download handler chybi
     */
    public function testItReturns404WithoutHostHandler(): void
    {
        $response = $this->controller(new PublicCmsAttachmentDownloadHandlerRegistry())->download(14);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    /**
     * Vytvori controller se skutecnym request-scoped containerem a response factory
     */
    private function controller(PublicCmsAttachmentDownloadHandlerRegistry $registry): PublicCmsAttachmentDownloadController
    {
        $factory = new Psr17Factory();

        return new PublicCmsAttachmentDownloadController(
            $registry,
            new Container(),
            new Responses(new ResponseBuilder($factory, $factory)),
        );
    }
}

/**
 * Vraci verejnou attachment odpoved pro prijatou file identity
 */
final readonly class RecordingAttachmentDownloadHandler implements PublicCmsAttachmentDownloadHandlerInterface
{
    /**
     * Vraci file identity v testovaci HTTP odpovedi
     */
    public function downloadAttachment(int $fileId): ResponseInterface
    {
        return new Response(body: (string) $fileId);
    }
}
