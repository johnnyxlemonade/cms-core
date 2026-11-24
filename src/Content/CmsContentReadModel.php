<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Framework\Database\Database;

/**
 * Reads nonhierarchical CMS content for editors and administrative checks
 */
final readonly class CmsContentReadModel
{
    private CmsContentQueries $queries;

    /**
     * Receives the database and registered module definition
     */
    public function __construct(private Database $database, private CmsContentDefinition $definition)
    {
        $this->queries = new CmsContentQueries($database, $definition);
    }

    /**
     * Checks whether a nondeleted content record exists
     */
    public function exists(int $id): bool
    {
        return $this->queries->scope($this->database->table($this->definition->getRootTable()))->where('id', $id)->where('deleted_at', null)->exists();
    }

    /**
     * Returns the owner, including for a soft deleted record
     */
    public function ownerId(int $id): ?int
    {
        $row = $this->queries->scope($this->database->table($this->definition->getRootTable()))->select(['owner_id'])->where('id', $id)->first();

        return $row === null || $row['owner_id'] === null ? null : (int) $row['owner_id'];
    }

    /**
     * Returns root domain values from attribute mappings
     *
     * @return array<string,mixed>|null
     */
    public function metadata(int $id): ?array
    {
        $columns = (new CmsDomainMapping($this->definition->getDataClass()))->columns();
        $row = $this->queries->scope($this->database->table($this->definition->getRootTable()))->select(['id', ...array_keys(CmsContentInput::DISPLAY_DEFAULTS), ...array_values($columns['root'])])
            ->where('id', $id)->where('deleted_at', null)->first();
        if ($row !== null) {
            unset($row['id']);
        }

        return $row;
    }

    /**
     * Returns a stored translation with domain values and canonical URL
     *
     * @return array<string,mixed>|null
     */
    public function translation(int $id, string $locale): ?array
    {
        return $this->queries->translations()->where('t.' . $this->definition->getTranslationForeignKey(), $id)
            ->where('t.locale', $locale)->first();
    }

    /**
     * Returns the stored publication state and timestamp
     *
     * @return array<string,mixed>|null
     */
    public function publication(int $id, string $locale): ?array
    {
        if ($this->definition->rootScope() !== [] && !$this->exists($id)) {
            return null;
        }
        return $this->database->table($this->definition->getTranslationTable())->select(['publication_state', 'published_at'])
            ->where($this->definition->getTranslationForeignKey(), $id)->where('locale', $locale)->first();
    }

    /**
     * Loads editor data, translations, canonical routes and ordered tags
     *
     * @return array<string,mixed>|null
     */
    public function findForEditor(int $id): ?array
    {
        $root = $this->queries->root()->where('a.id', $id)->where('a.deleted_at', null)->first();
        if ($root === null) {
            return null;
        }
        $translations = $this->queries->translations()->where('t.' . $this->definition->getTranslationForeignKey(), $id)
            ->orderBy('t.locale', 'ASC')->getArray();
        $tags = $this->database->table($this->definition->getTagTable() . ' tag')
            ->select(['tag.translation_id', 'tag.name'])
            ->joinOn($this->definition->getTranslationTable() . ' t', 't.id', '=', 'tag.translation_id')
            ->where('t.' . $this->definition->getTranslationForeignKey(), $id)
            ->orderBy('tag.sort_order', 'ASC')->orderBy('tag.id', 'ASC')->getArray();
        $byTranslation = [];
        foreach ($tags as $tag) {
            $byTranslation[(int) $tag['translation_id']][] = (string) $tag['name'];
        }
        foreach ($translations as &$translation) {
            $translation['tags'] = $byTranslation[(int) $translation['id']] ?? [];
            unset($translation['id'], $translation[$this->definition->getTranslationForeignKey()]);
        }
        unset($translation);
        $root['translations'] = $translations;

        return $root;
    }

    /**
     * Returns ordered unique tag names for a content locale
     *
     * @return list<string>
     */
    public function tagNamesForLocale(string $locale): array
    {
        /**
         * @var list<array{name:string}> $tags
         */
        $tags = $this->queries->scope($this->database->table($this->definition->getTagTable() . ' tag'), 'scope_root')
            ->select(['tag.name'])
            ->joinOn(
                $this->definition->getTranslationTable() . ' translation',
                'translation.id',
                '=',
                'tag.translation_id',
            )
            ->joinOn($this->definition->getRootTable() . ' scope_root', 'scope_root.id', '=', 'translation.' . $this->definition->getTranslationForeignKey())
            ->where('translation.locale', $locale)
            ->groupBy('tag.name')
            ->orderBy('tag.name', 'ASC')
            ->getArray();

        return array_map(
            static fn(array $tag): string => (string) $tag['name'],
            $tags,
        );
    }

    /**
     * Returns editor locales including stored inactive translations
     *
     * @return list<array{code:string,name:string,enabled:int,is_default:int,has_translation:int}>
     */
    public function editorLocales(int $articleId): array
    {
        if ($this->definition->rootScope() !== [] && !$this->exists($articleId)) {
            return [];
        }
        /**
         * @var list<array{code:string,name:string,enabled:int,is_default:int,has_translation:int}> $locales
         */
        $locales = $this->database->table('system_language language')
            ->select([
                'language.code',
                'language.name',
                'language.enabled',
                'language.is_default',
            ])
            ->selectRaw('CASE WHEN translation.id IS NULL THEN 0 ELSE 1 END AS has_translation')
            ->joinRaw(
                table: $this->definition->getTranslationTable() . ' translation',
                condition: 'translation.locale = language.code AND translation.' . $this->definition->getTranslationForeignKey() . ' = ?',
                bindings: [$articleId],
                type: 'LEFT',
            )
            ->whereRaw('language.enabled = 1 OR translation.id IS NOT NULL')
            ->orderBy('language.sort_order', 'ASC')
            ->orderBy('language.code', 'ASC')
            ->getArray();

        return $locales;
    }

    /**
     * Returns active locales and inactive locales with stored CMS translations
     *
     * @return list<array{code:string,name:string}>
     */
    public function dataGridLocales(): array
    {
        $query = $this->database->table('system_language language')
            ->select(['language.code', 'language.name']);
        if ($this->definition->rootScope() === []) {
            $query = $query->joinOn(
                $this->definition->getTranslationTable() . ' translation',
                'translation.locale',
                '=',
                'language.code',
                'LEFT',
            )
                ->whereRaw('language.enabled = 1 OR translation.id IS NOT NULL')
                ->groupBy('language.code')
                ->groupBy('language.name')
                ->groupBy('language.sort_order');
        } else {
            $scope = '';
            $bindings = [];
            foreach ($this->definition->rootScope() as $column => $value) {
                $scope .= ' AND scope_root.' . $column . ' = ?';
                $bindings[] = $value;
            }
            $query = $query->whereRaw(
                'language.enabled = 1 OR EXISTS (SELECT 1 FROM ' . $this->definition->getTranslationTable() . ' translation'
                . ' JOIN ' . $this->definition->getRootTable() . ' scope_root ON scope_root.id = translation.' . $this->definition->getTranslationForeignKey()
                . ' WHERE translation.locale = language.code' . $scope . ')',
                $bindings,
            );
        }
        /**
         * @var list<array{code:string,name:string}> $locales
         */
        $locales = $query->orderBy('language.sort_order', 'ASC')->orderBy('language.code', 'ASC')->getArray();

        return $locales;
    }

    /**
     * Returns enabled content locales for editor tabs and filters
     *
     * @return list<array<string,mixed>>
     */
    public function enabledLocales(): array
    {
        return $this->database->table('system_language')
            ->select(['code', 'name'])
            ->where('enabled', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->getArray();
    }
}
