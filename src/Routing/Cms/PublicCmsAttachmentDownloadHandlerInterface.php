<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Psr\Http\Message\ResponseInterface;

/**
 * Definuje hostem poskytovany download verejneho CMS attachmentu
 */
interface PublicCmsAttachmentDownloadHandlerInterface
{
    /**
     * Odesle attachment nebo vrati bezpecnou verejnou 404 odpoved
     */
    public function downloadAttachment(int $fileId): ResponseInterface;
}
