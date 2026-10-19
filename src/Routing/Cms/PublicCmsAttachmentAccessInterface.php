<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

/**
 * Overuje verejnou dostupnost CMS entity vlastnici attachmentu
 */
interface PublicCmsAttachmentAccessInterface
{
    /**
     * Urcuje zda je entita verejna alespon v jedne enabled lokalizaci
     */
    public function isPublicAttachmentOwner(int $entityId): bool;
}
