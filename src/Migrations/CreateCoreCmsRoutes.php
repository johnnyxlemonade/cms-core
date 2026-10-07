<?php

declare(strict_types=1);

namespace Lemonade\Cms\Migrations;

use Lemonade\Framework\Database\Migration\MigrationInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Vytvari kanonicke CMS routy Admin module platformy
 */
final class CreateCoreCmsRoutes implements MigrationInterface
{
    /**
     * Vraci stabilni identifikator migrace
     */
    public static function identifier(): string
    {
        return '20260914170900_create_core_cms_routes';
    }

    /**
     * Vytvari tabulku canonical CMS rout
     */
    public function up(Schema $schema): void
    {
        $schema->create('cms_route', static function (TableBlueprint $table): void {
            $table->id()->comment('Interni identifikator kanonicke routy');
            $table->string('module_code', 100)->comment('Modul, ktery cilovy zaznam vlastni');
            $table->unsignedBigInteger('entity_id')->comment('Identifikator ciloveho zaznamu modulu');
            $table->string('locale', 35)->comment('Jazyk kanonicke routy');
            $table->string('path', 700)->comment('Kanonicka verejna cesta bez uvodniho lomitka');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['locale', 'path'], 'uq_cms_route_locale_path');
            $table->unique(['module_code', 'entity_id', 'locale'], 'uq_cms_route_target');
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->comment('Lemonade / kanonicke CMS routy');
        }, ifNotExists: true);
    }
}
