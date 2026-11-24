<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Attribute;

/**
 * Maps a domain value to a root record column
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CmsRoot
{
    /**
     * Receives the column name and optional decimal precision
     */
    public function __construct(
        private string $column,
        private ?int $decimalScale = null,
    ) {}

    /**
     * Returns mapped root column
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
