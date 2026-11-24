<?php

declare(strict_types=1);

namespace Lemonade\Cms\Http\Controller;

use Lemonade\Cms\Routing\Cms\PublicCmsAttachmentDownloadHandlerRegistry;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Response\Responses;
use Psr\Http\Message\ResponseInterface;

/**
 * Dispatches public attachment downloads into the host request scope
 */
final readonly class PublicCmsAttachmentDownloadController
{
    /**
     * Receives the host handler factory, request scope and not found response
     */
    public function __construct(
        private PublicCmsAttachmentDownloadHandlerRegistry $handlers,
        private ContainerInterface $container,
        private Responses $responses,
    ) {}

    /**
     * Downloads a host-authorized attachment or hides its existence
     */
    public function download(int $fileId): ResponseInterface
    {
        return $this->handlers->handler($this->container)?->downloadAttachment($fileId)
            ?? $this->responses->text('', HttpStatus::NOT_FOUND->value);
    }
}
