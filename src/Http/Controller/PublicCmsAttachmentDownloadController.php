<?php

declare(strict_types=1);

namespace Lemonade\Cms\Http\Controller;

use Lemonade\Cms\Routing\Cms\PublicCmsAttachmentDownloadHandlerRegistry;
use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;

/**
 * Dispatchuje public attachment download do host request scope
 */
final readonly class PublicCmsAttachmentDownloadController
{
    /**
     * Nastavuje host handler factory, request scope, locale a 404 odpoved
     */
    public function __construct(
        private PublicCmsAttachmentDownloadHandlerRegistry $handlers,
        private ContainerInterface $container,
        private PublicLocaleResolution $locale,
        private Responses $responses,
    ) {}

    /**
     * Odesle hostem autorizovany attachment nebo bezpecne skryje jeho existenci
     */
    public function download(int $file): ResponseInterface
    {
        return $this->handlers->handler($this->container)?->downloadAttachment($file, $this->locale) ?? $this->responses->text('', 404);
    }
}
