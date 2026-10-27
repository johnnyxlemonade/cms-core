<?php

declare(strict_types=1);

namespace Lemonade\Cms;

/**
 * Rozlisuje novou, rozpracovanou a verejne publikovanou content mutaci
 */
enum CmsPublicationState: int
{
    case New = 0;
    case Draft = 1;
    case Published = 2;

    /**
     * Stavy dostupne v CMS editoru a jejich prekladove klice
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
     * Interni novy zaznam se v editoru chova jako koncept
     */
    public function editorValue(): string
    {
        return (string) ($this === self::New ? self::Draft->value : $this->value);
    }
}
