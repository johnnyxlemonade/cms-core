<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Builds shared CMS queries from a registered content definition
 */
final readonly class CmsContentQueries
{
    public const ROOT_COLUMNS = ['id', 'created_by', 'updated_by', 'deleted_by', 'owner_id', 'created_at', 'updated_at', 'deleted_at'];
    public const LOCALIZED_COLUMNS = ['id', 'locale', 'title', 'page_title', 'summary', 'content', 'meta_description', 'publication_state', 'published_at'];

    /**
     * Receives the database and registered content definition
     */
    public function __construct(private Database $database, private CmsContentDefinition $definition) {}

    /**
     * Builds a root query with domain columns and audit identities, including deleted rows
     */
    public function root(): QueryBuilder
    {
        $columns = (new CmsDomainMapping($this->definition->getDataClass()))->columns();
        $query = $this->database->table($this->definition->getRootTable() . ' a')
            ->select(array_map(static fn(string $column): string => 'a.' . $column, [...self::ROOT_COLUMNS, ...array_keys(CmsContentInput::DISPLAY_DEFAULTS), ...array_values($columns['root'])]));
        foreach (['creator' => 'created_by', 'updater' => 'updated_by', 'deleter' => 'deleted_by', 'owner' => 'owner_id'] as $alias => $column) {
            $name = $alias === 'owner' ? 'owner_name' : $column . '_name';
            $query = $query->joinOn('system_user ' . $alias, $alias . '.id', '=', 'a.' . $column, 'LEFT')
                ->selectRaw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$alias}.first_name, {$alias}.last_name)), ''), {$alias}.email, '—') AS {$name}");
        }

        return $this->scope($query, 'a');
    }

    /**
     * Builds a translation query with shared and domain columns and canonical URL
     */
    public function translations(): QueryBuilder
    {
        $columns = (new CmsDomainMapping($this->definition->getDataClass()))->columns();
        $localizedColumns = $this->definition->hasBodyColumns()
            ? self::LOCALIZED_COLUMNS
            : array_values(array_diff(self::LOCALIZED_COLUMNS, ['summary', 'content']));
        $query = $this->database->table($this->definition->getTranslationTable() . ' t')
            ->select(array_map(static fn(string $column): string => 't.' . $column, [
                ...$localizedColumns, $this->definition->getTranslationForeignKey(), ...array_values($columns['localized']),
            ]));

        if ($this->definition->rootScope() !== []) {
            $query = $query->joinOn($this->definition->getRootTable() . ' scope_root', 'scope_root.id', '=', 't.' . $this->definition->getTranslationForeignKey());
            $query = $this->scope($query, 'scope_root');
        }

        return $this->withCanonicalRoute($query);
    }

    /**
     * Joins the canonical route to a translation aliased as t
     */
    public function withCanonicalRoute(QueryBuilder $query): QueryBuilder
    {
        return $query->joinRaw(
            table: 'cms_route route',
            condition: 'route.module_code = ? AND route.entity_id = t.' . $this->definition->getTranslationForeignKey() . ' AND route.locale = t.locale',
            bindings: [$this->definition->getModuleCode()],
            type: 'LEFT',
        )->selectRaw('route.path AS canonical_path');
    }

    /**
     * Restricts a query to the module's partition of a shared table
     */
    public function scope(QueryBuilder $query, string $alias = ''): QueryBuilder
    {
        foreach ($this->definition->rootScope() as $column => $value) {
            $query = $query->where(($alias === '' ? '' : $alias . '.') . $column, $value);
        }

        return $query;
    }
}
