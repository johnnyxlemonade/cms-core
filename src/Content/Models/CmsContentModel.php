<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content\Models;

use Lemonade\Cms\CmsPublicationState;
use Lemonade\Cms\Content\CmsContentDefinition;
use Lemonade\Framework\Database\Database;

/**
 * Uklada spolecne CMS agregaty do oddelenych tabulek konkretniho modulu
 */
final readonly class CmsContentModel
{
    /**
     * Nastavuje databazi a tabulky obsahu
     */
    public function __construct(
        private Database $database,
        private CmsContentDefinition $definition,
    ) {}

    /**
     * Zalozi root a prvni prazdnou jazykovou mutaci
     */
    public function createDraft(int $userId, string $locale, string $now): int
    {
        $this->database->table($this->definition->rootTable)->insert([
            'created_by' => $userId,
            'owner_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->database->lastInsertId();
        $this->database->table($this->definition->translationTable)->insert([
            $this->definition->translationForeignKey => $id,
            'locale' => $locale,
            'publication_state' => CmsPublicationState::New->value,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * Zamkne existujici root vcetne domenovych hodnot
     *
     * @return array<string,mixed>|null
     */
    public function rootForUpdate(int $id): ?array
    {
        return $this->database->table($this->definition->rootTable)
            ->where('id', $id)->where('deleted_at', null)->lockForUpdate()->first();
    }

    /**
     * Zamkne root vcetne smazaneho zaznamu pro lifecycle
     *
     * @return array<string,mixed>|null
     */
    public function lifecycleForUpdate(int $id): ?array
    {
        return $this->database->table($this->definition->rootTable)
            ->where('id', $id)->lockForUpdate()->first();
    }

    /**
     * Zamkne existujici jazykovou mutaci
     *
     * @return array<string,mixed>|null
     */
    public function translationForUpdate(int $id, string $locale): ?array
    {
        return $this->database->table($this->definition->translationTable)
            ->where($this->definition->translationForeignKey, $id)
            ->where('locale', $locale)->lockForUpdate()->first();
    }

    /**
     * Vrati stitky jedne mutace v ulozenem poradi
     *
     * @return list<string>
     */
    public function tags(int $translationId): array
    {
        $rows = $this->database->table($this->definition->tagTable)
            ->select(['name'])->where('translation_id', $translationId)
            ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->getArray();

        return array_map(static fn(array $row): string => (string) $row['name'], $rows);
    }

    /**
     * Nahradi stitky jedne mutace v predanem poradi
     *
     * @param list<string> $tags
     */
    public function saveTags(int $translationId, array $tags, string $now): void
    {
        $this->database->table($this->definition->tagTable)->where('translation_id', $translationId)->delete();
        foreach ($tags as $position => $tag) {
            $this->database->table($this->definition->tagTable)->insert([
                'translation_id' => $translationId,
                'name' => $tag,
                'sort_order' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Ulozi zmenene root hodnoty v aktualni transakci
     *
     * @param array<string,mixed> $values
     */
    public function saveRoot(int $id, array $values, string $now): void
    {
        $values['updated_at'] = $now;
        $this->database->table($this->definition->rootTable)->where('id', $id)->set($values)->update();
    }

    /**
     * Vlozi nebo aktualizuje jednu mutaci a vrati jeji ID
     *
     * @param array<string,mixed> $values
     */
    public function saveTranslation(int $id, string $locale, ?int $translationId, array $values, string $now): int
    {
        $values['updated_at'] = $now;
        if ($translationId !== null) {
            $this->database->table($this->definition->translationTable)
                ->where('id', $translationId)->where($this->definition->translationForeignKey, $id)
                ->where('locale', $locale)->set($values)->update();

            return $translationId;
        }
        $values[$this->definition->translationForeignKey] = $id;
        $values['locale'] = $locale;
        $values['publication_state'] = CmsPublicationState::New->value;
        $values['published_at'] = null;
        $values['created_at'] = $now;
        $this->database->table($this->definition->translationTable)->insert($values);

        return (int) $this->database->lastInsertId();
    }

    /**
     * Ulozi publikaci konkretni mutace
     */
    public function savePublication(int $id, string $locale, CmsPublicationState $state, ?string $publishedAt, string $now): void
    {
        $this->database->table($this->definition->translationTable)
            ->where($this->definition->translationForeignKey, $id)->where('locale', $locale)
            ->set(['publication_state' => $state->value, 'published_at' => $publishedAt, 'updated_at' => $now])->update();
    }

    /**
     * Nastavi nepublikovanou mutaci po ulozeni na koncept
     */
    public function promoteDraft(int $id, string $locale, string $now): void
    {
        $this->database->table($this->definition->translationTable)
            ->where($this->definition->translationForeignKey, $id)->where('locale', $locale)
            ->where('publication_state !=', CmsPublicationState::Published->value)
            ->set(['publication_state' => CmsPublicationState::Draft->value, 'updated_at' => $now])->update();
    }

    /**
     * Obnovi root a vsechny mutace jako koncept
     */
    public function restoreDraft(int $id, int $userId, string $now): void
    {
        $this->saveRoot($id, ['deleted_at' => null, 'deleted_by' => null, 'updated_by' => $userId], $now);
        $this->database->table($this->definition->translationTable)
            ->where($this->definition->translationForeignKey, $id)
            ->set(['publication_state' => CmsPublicationState::Draft->value, 'published_at' => null, 'updated_at' => $now])->update();
    }
}
