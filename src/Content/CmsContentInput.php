<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use InvalidArgumentException;

/**
 * Predava spolecny obsah a volitelnou publikaci jedne jazykove mutace
 */
final readonly class CmsContentInput
{
    public const DISPLAY_DEFAULTS = [
        'recommended' => 0,
        'show_author' => 1,
        'sharing_enabled' => 1,
        'show_published_at' => 1,
        'show_thumbnail' => 1,
        'show_reading_time' => 1,
        'show_tags' => 1,
    ];

    /**
     * Nastavuje obsah editoru pred domenovou validaci a ulozenim
     *
     * @param array<string,int> $displaySettings
     */
    public function __construct(
        public string $locale,
        public string $title,
        public ?string $pageTitle,
        public ?string $summary,
        public ?string $content,
        public ?string $metaDescription,
        public mixed $tags,
        public bool $hasPublication,
        public mixed $publicationState,
        public mixed $publishedAt,
        public array $displaySettings = [],
    ) {
        foreach ($displaySettings as $column => $value) {
            if (!array_key_exists($column, self::DISPLAY_DEFAULTS) || !in_array($value, [0, 1], true)) {
                throw new InvalidArgumentException('CMS display setting is invalid.');
            }
        }
    }

    /**
     * Pripravi spolecne hodnoty ze standardniho editoroveho payloadu
     *
     * @param array<string,mixed> $data
     */
    public static function fromEditor(array $data): self
    {
        return new self(
            locale: strtolower(trim((string) ($data['locale'] ?? ''))),
            title: trim((string) ($data['title'] ?? '')),
            pageTitle: self::nullableText($data['page_title'] ?? null),
            summary: self::nullableText($data['summary'] ?? null),
            content: self::nullableText($data['content'] ?? null),
            metaDescription: self::nullableText($data['meta_description'] ?? null),
            tags: $data['tags'] ?? null,
            hasPublication: array_key_exists('publication_state', $data),
            publicationState: $data['publication_state'] ?? null,
            publishedAt: $data['published_at'] ?? null,
            displaySettings: self::displaySettings($data),
        );
    }

    /** @param array<string,mixed> $data
     *  @return array<string,int>
     */
    private static function displaySettings(array $data): array
    {
        $settings = [];
        foreach (array_keys(self::DISPLAY_DEFAULTS) as $name) {
            if (array_key_exists($name, $data)) {
                $settings[$name] = (int) in_array((string) $data[$name], ['1', 'true', 'on'], true);
            }
        }

        return $settings;
    }

    /**
     * Prevede prazdny volitelny text na null
     */
    private static function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
