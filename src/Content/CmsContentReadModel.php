<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Framework\Database\Database;

/**
 * Nacita nehierarchicky CMS obsah pro editor a administracni kontroly
 */
final readonly class CmsContentReadModel
{
    private CmsContentQueries $queries;

    /**
     * Nastavuje databazi a registrovanou definici modulu
     */
    public function __construct(private Database $database, private CmsContentDefinition $definition)
    {
        $this->queries = new CmsContentQueries($database, $definition);
    }

    /**
     * Overi existenci nesmazane identity obsahu
     */
    public function exists(int $id): bool
    {
        return $this->database->table($this->definition->rootTable)->where('id', $id)->where('deleted_at', null)->exists();
    }

    /**
     * Vrati vlastnika i pro soft-deleted zaznam
     */
    public function ownerId(int $id): ?int
    {
        $row = $this->database->table($this->definition->rootTable)->select(['owner_id'])->where('id', $id)->first();

        return $row === null || $row['owner_id'] === null ? null : (int) $row['owner_id'];
    }

    /**
     * Vrati globalni domenove hodnoty podle atributoveho mapovani
     *
     * @return array<string,mixed>|null
     */
    public function metadata(int $id): ?array
    {
        $columns = (new CmsDomainMapping($this->definition->dataClass))->columns();
        $row = $this->database->table($this->definition->rootTable)->select(['id', ...array_values($columns['root'])])
            ->where('id', $id)->where('deleted_at', null)->first();
        if ($row !== null) {
            unset($row['id']);
        }

        return $row;
    }

    /**
     * Vrati ulozenou mutaci vcetne domenovych hodnot a canonical URL
     *
     * @return array<string,mixed>|null
     */
    public function translation(int $id, string $locale): ?array
    {
        return $this->queries->translations()->where('t.' . $this->definition->translationForeignKey, $id)
            ->where('t.locale', $locale)->first();
    }

    /**
     * Vrati stav a datum publikace ulozene mutace
     *
     * @return array<string,mixed>|null
     */
    public function publication(int $id, string $locale): ?array
    {
        return $this->database->table($this->definition->translationTable)->select(['publication_state', 'published_at'])
            ->where($this->definition->translationForeignKey, $id)->where('locale', $locale)->first();
    }

    /**
     * Nacte editor, vsechny mutace, canonical cesty a serazene stitky
     *
     * @return array<string,mixed>|null
     */
    public function findForEditor(int $id): ?array
    {
        $root = $this->queries->root()->where('a.id', $id)->where('a.deleted_at', null)->first();
        if ($root === null) {
            return null;
        }
        $translations = $this->queries->translations()->where('t.' . $this->definition->translationForeignKey, $id)
            ->orderBy('t.locale', 'ASC')->getArray();
        $tags = $this->database->table($this->definition->tagTable . ' tag')
            ->select(['tag.translation_id', 'tag.name'])
            ->joinOn($this->definition->translationTable . ' t', 't.id', '=', 'tag.translation_id')
            ->where('t.' . $this->definition->translationForeignKey, $id)
            ->orderBy('tag.sort_order', 'ASC')->orderBy('tag.id', 'ASC')->getArray();
        $byTranslation = [];
        foreach ($tags as $tag) {
            $byTranslation[(int) $tag['translation_id']][] = (string) $tag['name'];
        }
        foreach ($translations as &$translation) {
            $translation['tags'] = $byTranslation[(int) $translation['id']] ?? [];
            unset($translation['id'], $translation[$this->definition->translationForeignKey]);
        }
        unset($translation);
        $root['translations'] = $translations;

        return $root;
    }

    /**
     * Vrati serazene unikatni nazvy stitku pro zvolenou content lokalizaci
     *
     * @return list<string>
     */
    public function tagNamesForLocale(string $locale): array
    {
        /** @var list<array{name:string}> $tags */
        $tags = $this->database->table($this->definition->tagTable . ' tag')
            ->select(['tag.name'])
            ->joinOn(
                $this->definition->translationTable . ' translation',
                'translation.id',
                '=',
                'tag.translation_id',
            )
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
     * Vrati locale dostupne pro editor vcetne ulozenych neaktivnich mutaci
     *
     * @return list<array{code:string,name:string,enabled:int,is_default:int,has_translation:int}>
     */
    public function editorLocales(int $articleId): array
    {
        /** @var list<array{code:string,name:string,enabled:int,is_default:int,has_translation:int}> $locales */
        $locales = $this->database->table('system_language language')
            ->select([
                'language.code',
                'language.name',
                'language.enabled',
                'language.is_default',
            ])
            ->selectRaw('CASE WHEN translation.id IS NULL THEN 0 ELSE 1 END AS has_translation')
            ->joinRaw(
                table: $this->definition->translationTable . ' translation',
                condition: 'translation.locale = language.code AND translation.' . $this->definition->translationForeignKey . ' = ?',
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
     * Vrati aktivni locale a deaktivovane locale s existujici CMS mutaci
     *
     * @return list<array{code:string,name:string}>
     */
    public function dataGridLocales(): array
    {
        /** @var list<array{code:string,name:string}> $locales */
        $locales = $this->database->table('system_language language')
            ->select(['language.code', 'language.name'])
            ->joinOn(
                $this->definition->translationTable . ' translation',
                'translation.locale',
                '=',
                'language.code',
                'LEFT',
            )
            ->whereRaw('language.enabled = 1 OR translation.id IS NOT NULL')
            ->groupBy('language.code')
            ->groupBy('language.name')
            ->groupBy('language.sort_order')
            ->orderBy('language.sort_order', 'ASC')
            ->orderBy('language.code', 'ASC')
            ->getArray();

        return $locales;
    }

    /**
     * Vrati enabled content locale pro taby editoru a deklarovany filter
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
