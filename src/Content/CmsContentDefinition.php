<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use InvalidArgumentException;

/**
 * Defines the identity and tables of one nonhierarchical CMS module
 */
final readonly class CmsContentDefinition
{
    /**
     * Sets the module identity, owned tables and optional body columns
     *
     * @param class-string $dataClass
     * @param array<string,int|string> $rootScope
     * @param array<string,string> $routePrefixes
     */
    public function __construct(
        private string $moduleCode,
        private string $entityType,
        private string $rootTable,
        private string $translationTable,
        private string $translationForeignKey,
        private string $tagTable,
        private string $translationUniqueConstraint,
        private string $validationPrefix,
        private string $dataClass,
        private array $rootScope = [],
        private array $routePrefixes = [],
        private bool $hasBodyColumns = true,
    ) {
        foreach ([$entityType, $rootTable, $translationTable, $translationForeignKey, $tagTable, $translationUniqueConstraint, $validationPrefix] as $identifier) {
            if (preg_match('/^[a-z][a-z0-9_]*$/D', $identifier) !== 1) {
                throw new InvalidArgumentException('Invalid CMS content identifier.');
            }
        }
        if (preg_match('/^cms\.[a-z][a-z0-9_]*$/D', $moduleCode) !== 1) {
            throw new InvalidArgumentException('Invalid CMS content module code.');
        }
        foreach ($rootScope as $column => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*$/D', $column) !== 1 || (!is_int($value) && !is_string($value))) {
                throw new InvalidArgumentException('Invalid CMS root scope.');
            }
        }
        foreach ($routePrefixes as $locale => $prefix) {
            if (preg_match('/^[a-z][a-z0-9-]{1,9}$/D', $locale) !== 1
                || strlen($prefix) > 180
                || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/D', $prefix) !== 1
            ) {
                throw new InvalidArgumentException('Invalid CMS route prefix.');
            }
        }
    }

    /**
     * Returns the owning CMS module code
     */
    public function getModuleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Returns the audited entity type
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * Returns the root record table
     */
    public function getRootTable(): string
    {
        return $this->rootTable;
    }

    /**
     * Returns the localized record table
     */
    public function getTranslationTable(): string
    {
        return $this->translationTable;
    }

    /**
     * Returns the foreign key from translations to root records
     */
    public function getTranslationForeignKey(): string
    {
        return $this->translationForeignKey;
    }

    /**
     * Returns the table containing localized tags
     */
    public function getTagTable(): string
    {
        return $this->tagTable;
    }

    /**
     * Returns the unique translation constraint name
     */
    public function getTranslationUniqueConstraint(): string
    {
        return $this->translationUniqueConstraint;
    }

    /**
     * Returns the translation prefix for validation errors
     */
    public function getValidationPrefix(): string
    {
        return $this->validationPrefix;
    }

    /**
     * Returns the domain data class
     *
     * @return class-string
     */
    public function getDataClass(): string
    {
        return $this->dataClass;
    }

    /**
     * Reports whether translations own the shared body columns
     */
    public function hasBodyColumns(): bool
    {
        return $this->hasBodyColumns;
    }

    /**
     * Returns the root record scope for a specific content type
     *
     * @return array<string,int|string>
     */
    public function rootScope(): array
    {
        return $this->rootScope;
    }

    /**
     * Returns the optional canonical path prefix for one locale
     */
    public function routePrefix(string $locale): string
    {
        return $this->routePrefixes[$locale] ?? '';
    }
}
