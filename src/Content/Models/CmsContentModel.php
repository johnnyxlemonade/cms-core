<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content\Models;

use Lemonade\Cms\CmsPublicationState;
use Lemonade\Cms\Content\CmsContentDefinition;
use Lemonade\Cms\Content\CmsContentQueries;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Persists shared CMS aggregates in module owned tables
 */
final readonly class CmsContentModel
{
    /**
     * Receives the database and content tables
     */
    public function __construct(
        private Database $database,
        private CmsContentDefinition $definition,
    ) {}

    /**
     * Creates a root record and its first empty translation
     */
    public function createDraft(int $userId, string $locale, string $now): int
    {
        $this->database->table($this->definition->getRootTable())->insert([
            'created_by' => $userId,
            'owner_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            ...$this->definition->rootScope(),
        ]);
        $id = (int) $this->database->lastInsertId();
        $this->database->table($this->definition->getTranslationTable())->insert([
            $this->definition->getTranslationForeignKey() => $id,
            'locale' => $locale,
            'publication_state' => CmsPublicationState::New->value,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * Locks an existing root record with domain values
     *
     * @return array<string,mixed>|null
     */
    public function rootForUpdate(int $id): ?array
    {
        return $this->rootQuery()
            ->where('id', $id)->where('deleted_at', null)->lockForUpdate()->first();
    }

    /**
     * Locks a root record, including deleted records, for lifecycle changes
     *
     * @return array<string,mixed>|null
     */
    public function lifecycleForUpdate(int $id): ?array
    {
        return $this->rootQuery()
            ->where('id', $id)->lockForUpdate()->first();
    }

    /**
     * Locks an existing translation
     *
     * @return array<string,mixed>|null
     */
    public function translationForUpdate(int $id, string $locale): ?array
    {
        return $this->database->table($this->definition->getTranslationTable())
            ->where($this->definition->getTranslationForeignKey(), $id)
            ->where('locale', $locale)->lockForUpdate()->first();
    }

    /**
     * Returns tags of one translation in stored order
     *
     * @return list<string>
     */
    public function tags(int $translationId): array
    {
        $rows = $this->database->table($this->definition->getTagTable())
            ->select(['name'])->where('translation_id', $translationId)
            ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->getArray();

        return array_map(static fn(array $row): string => (string) $row['name'], $rows);
    }

    /**
     * Replaces the tags of one translation in the supplied order
     *
     * @param list<string> $tags
     */
    public function saveTags(int $translationId, array $tags, string $now): void
    {
        $this->database->table($this->definition->getTagTable())->where('translation_id', $translationId)->delete();
        foreach ($tags as $position => $tag) {
            $this->database->table($this->definition->getTagTable())->insert([
                'translation_id' => $translationId,
                'name' => $tag,
                'sort_order' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Saves changed root values in the current transaction
     *
     * @param array<string,mixed> $values
     */
    public function saveRoot(int $id, array $values, string $now): void
    {
        $updates = [...$values, 'updated_at' => $now];
        $this->rootQuery()->where('id', $id)->set($updates)->update();
    }

    /**
     * Inserts or updates a translation and returns its identifier
     *
     * @param array<string,mixed> $values
     */
    public function saveTranslation(int $id, string $locale, ?int $translationId, array $values, string $now): int
    {
        $translationValues = [...$values, 'updated_at' => $now];
        if ($translationId !== null) {
            $this->database->table($this->definition->getTranslationTable())
                ->where('id', $translationId)->where($this->definition->getTranslationForeignKey(), $id)
                ->where('locale', $locale)->set($translationValues)->update();

            return $translationId;
        }
        $translationValues[$this->definition->getTranslationForeignKey()] = $id;
        $translationValues['locale'] = $locale;
        $translationValues['publication_state'] = CmsPublicationState::New->value;
        $translationValues['published_at'] = null;
        $translationValues['created_at'] = $now;
        $this->database->table($this->definition->getTranslationTable())->insert($translationValues);

        return (int) $this->database->lastInsertId();
    }

    /**
     * Saves the publication state of one translation
     */
    public function savePublication(int $id, string $locale, CmsPublicationState $state, ?string $publishedAt, string $now): void
    {
        $this->database->table($this->definition->getTranslationTable())
            ->where($this->definition->getTranslationForeignKey(), $id)->where('locale', $locale)
            ->set(['publication_state' => $state->value, 'published_at' => $publishedAt, 'updated_at' => $now])->update();
    }

    /**
     * Promotes an unpublished new translation to draft
     */
    public function promoteDraft(int $id, string $locale, string $now): void
    {
        $this->database->table($this->definition->getTranslationTable())
            ->where($this->definition->getTranslationForeignKey(), $id)->where('locale', $locale)
            ->where('publication_state !=', CmsPublicationState::Published->value)
            ->set(['publication_state' => CmsPublicationState::Draft->value, 'updated_at' => $now])->update();
    }

    /**
     * Restores the root and all translations as drafts
     */
    public function restoreDraft(int $id, int $userId, string $now): void
    {
        $this->saveRoot($id, ['deleted_at' => null, 'deleted_by' => null, 'updated_by' => $userId], $now);
        $this->database->table($this->definition->getTranslationTable())
            ->where($this->definition->getTranslationForeignKey(), $id)
            ->set(['publication_state' => CmsPublicationState::Draft->value, 'published_at' => null, 'updated_at' => $now])->update();
    }

    /**
     * Enforces the content partition for root locks and writes
     */
    private function rootQuery(): QueryBuilder
    {
        return (new CmsContentQueries($this->database, $this->definition))->scope($this->database->table($this->definition->getRootTable()));
    }
}
