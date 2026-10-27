<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Attribute;

/**
 * Mapuje domenovou hodnotu na sloupec korenoveho zaznamu
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CmsRoot
{
    /**
     * Nastavuje nazev sloupce a pripadnou presnost desetinne hodnoty
     */
    public function __construct(
        public string $column,
        public ?int $decimalScale = null,
    ) {}
}
