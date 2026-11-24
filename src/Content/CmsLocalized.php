<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Attribute;

/**
 * Maps a domain value to a translation column
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CmsLocalized
{
    /**
     * Receives the column name and optional decimal precision
     */
    public function __construct(
        private string $column,
        private ?int $decimalScale = null,
    ) {}

    /**
     * Returns mapped translation column
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Returns decimal precision when configured
     */
    public function getDecimalScale(): ?int
    {
        return $this->decimalScale;
    }
}
