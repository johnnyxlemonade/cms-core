<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use InvalidArgumentException;
use Lemonade\Cms\CmsPublicationState;

/**
 * Carries shared content and optional publication input for one locale
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
     * Creates validated shared editor input for one content locale
     *
     * @param array<string,int> $displaySettings
     */
    public function __construct(
        private string $locale,
        private string $title,
        private ?string $pageTitle,
        private ?string $summary,
        private ?string $content,
        private ?string $metaDescription,
        private mixed $tags,
        private bool $hasPublication,
        private mixed $publicationState,
        private mixed $publishedAt,
        private array $displaySettings = [],
    ) {
        foreach ($displaySettings as $column => $value) {
            if (!array_key_exists($column, self::DISPLAY_DEFAULTS) || !in_array($value, [0, 1], true)) {
                throw new InvalidArgumentException('CMS display setting is invalid.');
            }
        }
    }

    /**
     * Returns content locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns submitted title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns submitted browser page title
     */
    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    /**
     * Returns submitted summary
     */
    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * Returns submitted body content
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Returns submitted SEO description
     */
    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    /**
     * Returns submitted tag input
     */
    public function getTags(): mixed
    {
        return $this->tags;
    }

    /**
     * Reports whether publication state was submitted
     */
    public function hasPublication(): bool
    {
        return $this->hasPublication;
    }

    /**
     * Returns submitted publication state
     */
    public function getPublicationState(): mixed
    {
        return $this->publicationState;
    }

    /**
     * Returns submitted publication timestamp
     */
    public function getPublishedAt(): mixed
    {
        return $this->publishedAt;
    }

    /**
     * Returns validated display settings
     *
     * @return array<string,int>
     */
    public function getDisplaySettings(): array
    {
        return $this->displaySettings;
    }

    /**
     * Normalizes shared values from the editor payload
     *
     * @param array<string,mixed> $data
     */
    public static function fromEditor(array $data): self
    {
        $text = CmsContentValidation::texts($data, ['locale', 'title', 'page_title', 'summary', 'content', 'meta_description', 'published_at']);
        $errors = [];
        $state = $data['publication_state'] ?? null;
        if ($state !== null && !in_array($state, [
            CmsPublicationState::Draft->value,
            CmsPublicationState::Published->value,
            (string) CmsPublicationState::Draft->value,
            (string) CmsPublicationState::Published->value,
        ], true)) {
            $errors['publication_state'] = 'admin.cms_validation.publication_invalid';
        }
        if ($text['published_at'] !== null && !CmsContentValidation::validDate($text['published_at'])) {
            $errors['published_at'] = 'admin.cms_validation.date_invalid';
        }
        $tags = $data['tags'] ?? null;
        if ($tags !== null && !is_string($tags) && (!is_array($tags) || !array_is_list($tags))) {
            $errors['tags'] = 'admin.cms_validation.invalid';
        } elseif (is_array($tags)) {
            foreach ($tags as $tag) {
                if (!is_string($tag)) {
                    $errors['tags'] = 'admin.cms_validation.invalid';
                }
            }
        }
        CmsContentValidation::assert($errors);

        return new self(
            locale: strtolower($text['locale'] ?? ''),
            title: $text['title'] ?? '',
            pageTitle: $text['page_title'],
            summary: $text['summary'],
            content: $text['content'],
            metaDescription: $text['meta_description'],
            tags: $tags,
            hasPublication: $state !== null,
            publicationState: $state,
            publishedAt: $text['published_at'],
            displaySettings: self::displaySettings($data),
        );
    }

    /**
     * Returns validated root display settings from the editor payload
     *
     * @param array<string,mixed> $data
     * @return array<string,int>
     */
    private static function displaySettings(array $data): array
    {
        $settings = [];
        $errors = [];
        foreach (array_keys(self::DISPLAY_DEFAULTS) as $name) {
            if (isset($data[$name])) {
                if (!in_array($data[$name], [0, 1, false, true, '', '0', '1', 'false', 'true', 'off', 'on'], true)) {
                    $errors[$name] = 'admin.cms_validation.invalid';
                    continue;
                }
                $settings[$name] = (int) in_array($data[$name], [1, true, '1', 'true', 'on'], true);
            }
        }

        CmsContentValidation::assert($errors);

        return $settings;
    }
}
