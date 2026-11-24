<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Psr\Http\Message\ResponseInterface;

/**
 * Defines the host-provided public CMS attachment download
 */
interface PublicCmsAttachmentDownloadHandlerInterface
{
    /**
     * Downloads an attachment or returns a safe public not found response
     */
    public function downloadAttachment(int $fileId): ResponseInterface;
}
