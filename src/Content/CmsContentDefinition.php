<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use InvalidArgumentException;

/**
 * Urci identitu a tabulky jednoho nehierarchickeho CMS modulu
 */
final readonly class CmsContentDefinition
{
    /**
     * Nastavuje canonical kod, auditni identitu a vlastnene tabulky
     *
     * @param class-string $dataClass
     * @param array<string,int|string> $rootScope
     */
    public function __construct(
        public string $moduleCode,
        public string $entityType,
        public string $rootTable,
        public string $translationTable,
        public string $translationForeignKey,
        public string $tagTable,
        public string $translationUniqueConstraint,
        public string $validationPrefix,
        public string $dataClass,
        private array $rootScope = [],
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
    }

    /**
     * Vraci omezeni korenovych zaznamu na konkretni typ
     *
     * @return array<string,int|string>
     */
    public function rootScope(): array
    {
        return $this->rootScope;
    }
}
