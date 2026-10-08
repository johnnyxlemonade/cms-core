<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Framework\Database\Database;

/**
 * Vyhledava kanonicke CMS routy pro verejny transport modulu
 */
final class CmsRouteRepository implements CmsRouteRepositoryInterface
{
    /**
     * Nastavuje databazove uloziste kanonickych cest
     */
    public function __construct(private readonly Database $database) {}

    /**
     * Najde kanonickou cestu v pozadovane lokalizaci
     */
    public function find(string $locale, string $path): ?CmsRoute
    {
        $rows = $this->database->select(
            'SELECT id, module_code, entity_id, locale, path FROM cms_route WHERE locale = ? AND path = ? AND deleted_at IS NULL',
            [$locale, $path],
        );
        $row = $rows[0] ?? null;
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

    /**
     * Najde route vlastnene konkretnim module targetem bez ohledu na soft delete
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
     * Nacte routy jednoho locale a module prefixu vcetne rezervovanych smazanych cest
     *
     * @return list<CmsRoute>
     */
    public function routesForPrefix(string $locale, string $prefix): array
    {
        $routes = [];
        foreach ($this->database->select(
            'SELECT id, module_code, entity_id, locale, path FROM cms_route WHERE locale = ? AND path LIKE ?',
            [$locale, $prefix . '/%'],
        ) as $row) {
            $route = $this->route($row);
            if ($route !== null) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * Vlozi novou route nebo zmeni vyhradne cestu existujiciho stejneho targetu
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
     * Skryje routy smazane entity bez uvolneni jejich URL adres
     */
    public function softDeleteForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->database->statement(
            'UPDATE cms_route SET deleted_at = ?, updated_at = ? WHERE module_code = ? AND entity_id = ? AND deleted_at IS NULL',
            [$now, $now, $moduleCode, $entityId],
        );
    }

    /**
     * Znovu aktivuje driv rezervovane routy obnovene entity
     */
    public function restoreForTarget(string $moduleCode, int $entityId, string $now): void
    {
        $this->database->statement(
            'UPDATE cms_route SET deleted_at = NULL, updated_at = ? WHERE module_code = ? AND entity_id = ? AND deleted_at IS NOT NULL',
            [$now, $moduleCode, $entityId],
        );
    }

    /**
     * Prevede databazovy radek na canonical route nebo vrati null pro neplatna data
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
