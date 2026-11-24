<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Cms\CmsPublicationState;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Builds the public visibility scope for nonhierarchical routed CMS content
 */
final class PublishedContentScope
{
    /**
     * Prevents construction of a stateless query helper
     */
    private function __construct() {}

    /**
     * Restricts a module to visible translations with active canonical routes
     */
    public static function query(
        Database $database,
        CmsContentDefinition $definition,
        ?string $locale = null,
    ): QueryBuilder {
        $query = $database->table($definition->getRootTable() . ' article')
            ->joinOn(
                $definition->getTranslationTable() . ' translation',
                'translation.' . $definition->getTranslationForeignKey(),
                '=',
                'article.id',
            )
            ->joinOn('system_language language', 'language.code', '=', 'translation.locale')
            ->joinRaw(
                'cms_route route',
                'route.module_code = ? AND route.entity_id = article.id AND route.locale = translation.locale AND route.deleted_at IS NULL AND route.path <> ?',
                [$definition->getModuleCode(), ''],
            )
            ->where('article.deleted_at', null)
            ->where('language.enabled', 1)
            ->where('translation.publication_state', CmsPublicationState::Published->value)
            ->where('translation.published_at !=', null)
            ->where('translation.published_at <=', date('Y-m-d H:i:s'))
            ->whereRaw("TRIM(translation.title) <> ''");

        foreach ($definition->rootScope() as $column => $value) {
            $query = $query->where('article.' . $column, $value);
        }

        return $locale === null ? $query : $query->where('translation.locale', $locale);
    }
}
