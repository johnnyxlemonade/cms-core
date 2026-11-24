<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

/**
 * Checks public availability of the CMS entity owning an attachment
 */
interface PublicCmsAttachmentAccessInterface
{
    /**
     * Reports whether an entity is public in at least one enabled locale
     */
    public function isPublicAttachmentOwner(int $entityId): bool;
}
