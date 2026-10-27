<?php

declare(strict_types=1);

namespace Lemonade\Cms;

use InvalidArgumentException;

/**
 * Normalizuje lokalizovane stitky pri zachovani jejich poradi
 */
final class CmsTagNormalizer
{
    /**
     * Brani vytvareni instance stateless funkce
     */
    private function __construct() {}

    /**
     * Vrati jedine neprazdne stitky z pole nebo oddeleneho textu
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
