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
    ) {
        foreach ([$entityType, $rootTable, $translationTable, $translationForeignKey, $tagTable, $translationUniqueConstraint, $validationPrefix] as $identifier) {
            if (preg_match('/^[a-z][a-z0-9_]*$/D', $identifier) !== 1) {
                throw new InvalidArgumentException('Invalid CMS content identifier.');
            }
        }
        if (preg_match('/^cms\.[a-z][a-z0-9_]*$/D', $moduleCode) !== 1) {
            throw new InvalidArgumentException('Invalid CMS content module code.');
        }
    }
}
