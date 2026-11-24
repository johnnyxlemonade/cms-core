<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Cms\CmsPublicationState;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;

/**
 * Defines shared columns and references for CMS content tables
 */
final class CmsContentSchema
{
    /**
     * Prevents construction of a stateless helper
     */
    private function __construct() {}

    /**
     * Adds identity, ownership and soft delete columns to a root table
     */
    public static function root(TableBlueprint $table, string $rootTable): void
    {
        $table->id();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->unsignedBigInteger('deleted_by')->nullable();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->string('author_name', 255)->nullable();
        foreach (CmsContentInput::DISPLAY_DEFAULTS as $column => $default) {
            $table->boolean($column)->default($default);
        }
        $table->datetime('deleted_at')->nullable();
        $table->timestamps();
        $table->index(['deleted_at'], 'idx_' . $rootTable . '_deleted');
        $table->index(['owner_id', 'deleted_at'], 'idx_' . $rootTable . '_owner_deleted');
        foreach (['created_by', 'updated_by', 'deleted_by', 'owner_id'] as $column) {
            $suffix = $column === 'owner_id' ? 'owner' : $column;
            $table->foreign($column, 'fk_' . $rootTable . '_' . $suffix)
                ->references('id')->on('system_user')->nullOnDelete()->restrictOnUpdate();
        }
        $table->engine('InnoDB');
        $table->charset('utf8mb4');
    }

    /**
     * Adds localized content, SEO and publication columns, with optional body columns
     */
    public static function translation(TableBlueprint $table, string $rootTable, string $rootForeignKey, bool $hasBodyColumns = true): void
    {
        $table->id();
        $table->unsignedBigInteger($rootForeignKey);
        $table->string('locale', 10);
        $table->string('title', 180)->nullable();
        $table->string('page_title', 180)->nullable();
        if ($hasBodyColumns) {
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
        }
        $table->string('meta_description', 255)->nullable();
        $table->tinyInteger('publication_state')->unsigned()->default(CmsPublicationState::New->value);
        $table->datetime('published_at')->nullable();
        $table->timestamps();
        $table->unique([$rootForeignKey, 'locale'], 'uq_' . $rootTable . '_translation_locale');
        $table->index(['locale', 'title'], 'idx_' . $rootTable . '_translation_title');
        $table->index(['publication_state', 'published_at'], 'idx_' . $rootTable . '_translation_visibility');
        $table->foreign($rootForeignKey, 'fk_' . $rootTable . '_translation_article')
            ->references('id')->on($rootTable)->cascadeOnDelete()->restrictOnUpdate();
        $table->foreign('locale', 'fk_' . $rootTable . '_translation_locale')
            ->references('code')->on('system_language')->restrictOnDelete()->restrictOnUpdate();
        $table->engine('InnoDB');
        $table->charset('utf8mb4');
    }

    /**
     * Adds ordered tags for a translation
     */
    public static function tags(TableBlueprint $table, string $rootTable, string $translationTable): void
    {
        $table->id();
        $table->unsignedBigInteger('translation_id');
        $table->string('name', 255);
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
        $table->unique(['translation_id', 'name'], 'uq_' . $rootTable . '_translation_tag_name');
        $table->index(['translation_id', 'sort_order'], 'idx_' . $rootTable . '_translation_tag_sort');
        $table->foreign('translation_id', 'fk_' . $rootTable . '_translation_tag_translation')
            ->references('id')->on($translationTable)->cascadeOnDelete()->restrictOnUpdate();
        $table->engine('InnoDB');
        $table->charset('utf8mb4');
    }
}
