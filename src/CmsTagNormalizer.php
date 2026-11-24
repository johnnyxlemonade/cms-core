<?php

declare(strict_types=1);

namespace Lemonade\Cms;

use InvalidArgumentException;

/**
 * Normalizes localized tags while preserving their order
 */
final class CmsTagNormalizer
{
    /**
     * Prevents construction of a stateless helper
     */
    private function __construct() {}

    /**
     * Returns unique nonempty tags from an array or delimited text
     *
     * @return list<string>
     */
    public static function normalize(mixed $value): array
    {
        if (is_array($value)) {
            $source = $value;
        } else {
            $parsedTags = preg_split('/[\r\n,]+/', (string) $value);
            $source = $parsedTags === false ? [] : $parsedTags;
        }
        $tags = [];
        foreach ($source as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $name = trim($tag);
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 255) {
                throw new InvalidArgumentException('Tag exceeds 255 characters.');
            }
            if (!in_array($name, $tags, true)) {
                $tags[] = $name;
            }
        }

        return $tags;
    }
}
