<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Framework\Database\Database;

/**
 * Finds canonical CMS routes for public module transport
 */
final readonly class CmsRouteRepository implements CmsRouteRepositoryInterface
{
    /**
     * Receives the canonical route database storage
     */
    public function __construct(private Database $database) {}

    /**
     * Finds a canonical path in the requested locale
     */
    public function find(string $locale, string $path): ?CmsRoute
    {
        $rows = $this->database->select(
            'SELECT id, module_code, entity_id, locale, path FROM cms_route WHERE locale = ? AND path = ? AND deleted_at IS NULL',
            [$locale, $path],
        );
        return $this->route($rows[0] ?? null);
    }

    /**
     * Finds a module target's route including soft deleted routes
     */
    public function findForTarget(string $moduleCode, int $entityId, string $locale): ?CmsRoute
    {
        $rows = $this->database->select(
            'SELECT id, module_code, entity_id, locale, path FROM cms_route WHERE module_code = ? AND entity_id = ? AND locale = ?',
            [$moduleCode, $entityId, $locale],
        );

        return $this->route($rows[0] ?? null);
    }

    /**
     * Loads all locale routes including reserved deleted paths
     *
     * @return list<CmsRoute>
     */
    public function routesForLocale(string $locale): array
    {
        $routes = [];
        foreach ($this->database->select(
            'SELECT id, module_code, entity_id, locale, path FROM cms_route WHERE locale = ?',
            [$locale],
        ) as $row) {
            $route = $this->route($row);
            if ($route !== null) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * Inserts a route or changes only the path of its existing target
     */
    public function reserve(string $moduleCode, int $entityId, string $locale, string $path): void
    {
        $now = date('Y-m-d H:i:s');
        if ($this->findForTarget($moduleCode, $entityId, $locale) !== null) {
            $this->database->statement(
                'UPDATE cms_route SET path = ?, updated_at = ?, deleted_at = NULL WHERE module_code = ? AND entity_id = ? AND locale = ?',
                [$path, $now, $moduleCode, $entityId, $locale],
            );

            return;
        }

        $this->database->statement(
            'INSERT INTO cms_route(module_code, entity_id, locale, path, created_at, updated_at, deleted_at) VALUES (?, ?, ?, ?, ?, ?, NULL)',
            [$moduleCode, $entityId, $locale, $path, $now, $now],
        );
    }

    /**
     * Runs a callback in the shared database transaction
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        return $this->database->transaction(static fn(): mixed => $callback());
    }

    /**
     * Changes an existing target path without changing lifecycle state
     */
    public function updatePath(string $moduleCode, int $entityId, string $locale, string $path, string $now): void
    {
        $this->database->statement(
            'UPDATE cms_route SET path = ?, updated_at = ? WHERE module_code = ? AND entity_id = ? AND locale = ?',
            [$path, $now, $moduleCode, $entityId, $locale],
        );
    }

    /**
     * Hides deleted entity routes without releasing their URLs
     */
    public function softDeleteForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->database->statement(
            'UPDATE cms_route SET deleted_at = ?, updated_at = ? WHERE module_code = ? AND entity_id = ? AND deleted_at IS NULL',
            [$now, $now, $moduleCode, $entityId],
        );
    }

    /**
     * Reactivates reserved routes for a restored entity
     */
    public function restoreForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->database->statement(
            'UPDATE cms_route SET deleted_at = NULL, updated_at = ? WHERE module_code = ? AND entity_id = ? AND deleted_at IS NOT NULL',
            [$now, $moduleCode, $entityId],
        );
    }

    /**
     * Maps a database row to a canonical route or returns null for invalid data
     *
     * @param array<string,mixed>|null $row
     */
    private function route(?array $row): ?CmsRoute
    {
        if (!is_array($row)) {
            return null;
        }

        return new CmsRoute(
            (int) $row['id'],
            (string) $row['module_code'],
            (int) $row['entity_id'],
            (string) $row['locale'],
            (string) $row['path'],
        );
    }
}
