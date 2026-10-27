<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

/**
 * Predava spolecny obsah a volitelnou publikaci jedne jazykove mutace
 */
final readonly class CmsContentInput
{
    /**
     * Nastavuje obsah editoru pred domenovou validaci a ulozenim
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
    ) {}

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
        );
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
