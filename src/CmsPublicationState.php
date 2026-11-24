<?php

declare(strict_types=1);

namespace Lemonade\Cms;

/**
 * Distinguishes new, draft and published content translations
 */
enum CmsPublicationState: int
{
    case New = 0;
    case Draft = 1;
    case Published = 2;

    /**
     * Returns the editor states and their translation keys
     *
     * @return array<int,string>
     */
    public static function editorOptionLabelKeys(): array
    {
        return [
            (string) self::Draft->value => 'admin.status.draft',
            (string) self::Published->value => 'admin.status.published',
        ];
    }

    /**
     * Represents a new record as a draft in the editor
     */
    public function getEditorValue(): string
    {
        return (string) ($this === self::New ? self::Draft->value : $this->value);
    }
}
