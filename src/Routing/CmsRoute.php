<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

/**
 * Popisuje kanonickou verejnou cestu entity modulu
 */
final readonly class CmsRoute
{
    /**
     * Nastavuje identitu cilove entity a jeji lokalizovanou cestu
     */
    public function __construct(
        private int $id,
        private string $moduleCode,
        private int $entityId,
        private string $locale,
        private string $path,
    ) {}

    /**
     * Vrati interní identifikator CMS routy
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Vrati kod modulu vlastniciho cilovou entitu
     */
    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Vrati identifikator cilove entity modulu
     */
    public function entityId(): int
    {
        return $this->entityId;
    }

    /**
     * Vrati lokalizaci kanonicke cesty
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Vrati cestu bez uvodniho lomitka
     */
    public function path(): string
    {
        return $this->path;
    }
}
