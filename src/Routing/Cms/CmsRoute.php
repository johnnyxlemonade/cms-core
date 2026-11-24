<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

/**
 * Represents the canonical public path of a module entity
 */
final readonly class CmsRoute
{
    /**
     * Receives the target identity and localized path
     */
    public function __construct(
        private int $id,
        private string $moduleCode,
        private int $entityId,
        private string $locale,
        private string $path,
    ) {}

    /**
     * Returns the internal CMS route identifier
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Returns the code of the module owning the target entity
     */
    public function getModuleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Returns the module target identifier
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * Returns the canonical path locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns the path without a leading slash
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
