<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Content;

use Lemonade\Cms\Content\CmsContentSchema;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Driver\Mysql\MysqlIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSqlEscaper;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use PHPUnit\Framework\TestCase;

/**
 * Overuje spolecne fresh-install schema CMS obsahu
 */
final class CmsContentSchemaTest extends TestCase
{
    public function testSchemaContainsContentOwnershipPublicationAndTags(): void
    {
        $statements = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('query')->willReturnCallback(static function (string $statement) use (&$statements): bool {
            $statements[] = $statement;

            return true;
        });
        $config = DatabaseConfig::fromArray(['driver' => 'mysql']);
        $escaper = new MysqlIdentifierEscaper('');
        $schema = new Schema(new SchemaCompiler(new MysqlSchemaGrammar(new MysqlSqlEscaper($escaper), $config)), $driver);
        $schema->create('cms_example', static function (TableBlueprint $table): void {
            CmsContentSchema::root($table, 'cms_example');
            $table->decimal('price', 15, 2)->nullable();
        });
        $schema->create('cms_example_translation', static function (TableBlueprint $table): void {
            CmsContentSchema::translation($table, 'cms_example', 'item_id');
            $table->string('location', 255)->nullable();
        });
        $schema->create('cms_example_translation_tag', static function (TableBlueprint $table): void {
            CmsContentSchema::tags($table, 'cms_example', 'cms_example_translation');
        });

        self::assertCount(3, $statements);
        self::assertStringContainsString('`owner_id` BIGINT UNSIGNED NULL', $statements[0]);
        self::assertStringContainsString('`price` DECIMAL(15,2) NULL', $statements[0]);
        self::assertStringContainsString('`location` VARCHAR(255) NULL', $statements[1]);
        self::assertStringContainsString('`publication_state`', $statements[1]);
        self::assertStringContainsString('UNIQUE KEY `uq_cms_example_translation_locale` (`item_id`, `locale`)', $statements[1]);
        self::assertStringContainsString('UNIQUE KEY `uq_cms_example_translation_tag_name` (`translation_id`, `name`)', $statements[2]);
    }
}
