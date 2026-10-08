<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

/**
 * Nese lokalizovany slug a plnou canonical cestu pripravenou k rezervaci
 */
final readonly class CmsRouteReservation
{
    /**
     * Nastavuje slug entity a canonical path bez uvodniho lomitka
     */
    public function __construct(
        private string $slug,
        private string $path,
    ) {}

    /**
     * Vrati slug odpovidajici rezervovane canonical ceste
     */
    public function slug(): string
    {
        return $this->slug;
    }

    /**
     * Vrati canonical cestu bez uvodniho lomitka
     */
    public function path(): string
    {
        return $this->path;
    }
}
