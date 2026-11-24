<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Cms\CmsPublicationState;

/**
 * Writes domain values inside a locked CMS transaction
 */
interface CmsContentValueWriter
{
    /**
     * Validates and locks a definition before creating a root record
     */
    public function beforeCreateDraft(string $locale): void;

    /**
     * Initializes new draft values before recording creation
     */
    public function createDraft(int $id, string $locale): void;

    /**
     * Validates published values and reports whether they changed
     */
    public function prepare(int $id, string $locale, CmsPublicationState $state, object $domain): bool;

    /**
     * Saves prepared values after creating the translation in the same transaction
     */
    public function save(int $id, string $locale): void;
}
