<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Sklada standardni CMS projekce z registrovane definice a atributoveho mapovani
 */
final readonly class CmsContentQueries
{
    public const ROOT_COLUMNS = ['id', 'created_by', 'updated_by', 'deleted_by', 'owner_id', 'created_at', 'updated_at', 'deleted_at'];
    public const LOCALIZED_COLUMNS = ['id', 'locale', 'title', 'page_title', 'summary', 'content', 'meta_description', 'publication_state', 'published_at'];

    /**
     * Nastavuje databazi a registrovanou definici obsahu
     */
    public function __construct(private Database $database, private CmsContentDefinition $definition) {}

    /**
     * Vrati root vcetne domenovych sloupcu a auditnich identit bez filtru smazani
     */
    public function root(): QueryBuilder
    {
        $columns = (new CmsDomainMapping($this->definition->dataClass))->columns();
        $query = $this->database->table($this->definition->rootTable . ' a')
            ->select(array_map(static fn(string $column): string => 'a.' . $column, [...self::ROOT_COLUMNS, ...array_keys(CmsContentInput::DISPLAY_DEFAULTS), ...array_values($columns['root'])]));
        foreach (['creator' => 'created_by', 'updater' => 'updated_by', 'deleter' => 'deleted_by', 'owner' => 'owner_id'] as $alias => $column) {
            $name = $alias === 'owner' ? 'owner_name' : $column . '_name';
            $query = $query->joinOn('system_user ' . $alias, $alias . '.id', '=', 'a.' . $column, 'LEFT')
                ->selectRaw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name)), ''), {$alias}.email, '—') AS {$name}");
        }

        return $query;
    }

    /**
     * Vrati lokalizovane standardni a domenove sloupce s canonical URL
     */
    public function translations(): QueryBuilder
    {
        $columns = (new CmsDomainMapping($this->definition->dataClass))->columns();
        $query = $this->database->table($this->definition->translationTable . ' t')
            ->select(array_map(static fn(string $column): string => 't.' . $column, [
                ...self::LOCALIZED_COLUMNS, $this->definition->translationForeignKey, ...array_values($columns['localized']),
            ]));

        return $this->withCanonicalRoute($query);
    }

    /**
     * Pripoji canonical cestu k mutaci s internim aliasem t
     */
    public function withCanonicalRoute(QueryBuilder $query): QueryBuilder
    {
        return $query->joinRaw(
            table: 'cms_route route',
            condition: 'route.module_code = ? AND route.entity_id = t.' . $this->definition->translationForeignKey . ' AND route.locale = t.locale',
            bindings: [$this->definition->moduleCode],
            type: 'LEFT',
        )->selectRaw('route.path AS canonical_path');
    }
}
