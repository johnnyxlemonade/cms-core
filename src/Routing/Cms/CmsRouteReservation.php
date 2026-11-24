<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

/**
 * Carries a localized slug and canonical path ready for reservation
 */
final readonly class CmsRouteReservation
{
    /**
     * Receives the entity slug and canonical path without a leading slash
     */
    public function __construct(
        private string $slug,
        private string $path,
    ) {}

    /**
     * Returns the slug of the reserved canonical path
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Returns the canonical path without a leading slash
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
