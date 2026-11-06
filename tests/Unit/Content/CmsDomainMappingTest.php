<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Content;

use InvalidArgumentException;
use Lemonade\Cms\Content\CmsDomainMapping;
use Lemonade\Cms\Content\CmsLocalized;
use Lemonade\Cms\Content\CmsRoot;
use PHPUnit\Framework\TestCase;

/**
 * Overuje explicitni domenove mapovani pro root a jazykovou mutaci
 */
final class CmsDomainMappingTest extends TestCase
{
    public function testReadColumnsUseTheSameAttributesWithoutConstructingDomainData(): void
    {
        self::assertSame([
            'root' => ['startingPrice' => 'starting_price', 'highlighted' => 'highlighted', 'count' => 'count'],
            'localized' => ['location' => 'location'],
        ], (new CmsDomainMapping(MappedAuctionData::class))->columns());
    }

    public function testMapsAndComparesExactDecimalValues(): void
    {
        $mapping = new CmsDomainMapping(MappedAuctionData::class);
        $data = new MappedAuctionData('Praha', '1234567890123.5', true, null);

        self::assertSame([
            'root' => ['starting_price' => '1234567890123.50', 'highlighted' => 1, 'count' => null],
            'localized' => ['location' => 'Praha'],
        ], $mapping->values($data));
        self::assertSame(
            ['root' => false, 'localized' => false],
            $mapping->changes($data, ['starting_price' => '1234567890123.50', 'highlighted' => '1', 'count' => null], ['location' => 'Praha']),
        );
        self::assertSame(
            ['root' => false, 'localized' => true],
            $mapping->changes($data, ['starting_price' => '1234567890123.50', 'highlighted' => '1', 'count' => null], ['location' => 'Brno']),
        );
    }

    /**
     * Mapuje privatni oznacene hodnoty a vynecha vnitrni stav bez atributu
     */
    public function testMapsPrivatePropertiesWithoutPersistingInternalState(): void
    {
        $data = new readonly class ('Author', 'Prague', ['draft' => 'internal']) {
            /**
             * Nastavuje obsahove hodnoty a nemapovany vnitrni stav
             *
             * @param array<string,string> $fields
             */
            public function __construct(
                #[CmsRoot('author_name')]
                private string $authorName,
                #[CmsLocalized('location')]
                private string $location,
                private array $fields,
            ) {}

            /**
             * Vraci autora testovaciho zaznamu
             */
            public function authorName(): string
            {
                return $this->authorName;
            }

            /**
             * Vraci lokalizovane pracoviste
             */
            public function location(): string
            {
                return $this->location;
            }

            /**
             * Vraci nemapovany vstup zaznamu
             *
             * @return array<string,string>
             */
            public function fields(): array
            {
                return $this->fields;
            }
        };
        $mapping = new CmsDomainMapping($data::class);

        self::assertSame([
            'root' => ['authorName' => 'author_name'],
            'localized' => ['location' => 'location'],
        ], $mapping->columns());
        self::assertSame([
            'root' => ['author_name' => 'Author'],
            'localized' => ['location' => 'Prague'],
        ], $mapping->values($data));
        self::assertSame(
            ['root' => false, 'localized' => false],
            $mapping->changes($data, ['author_name' => 'Author'], ['location' => 'Prague']),
        );
        self::assertSame(
            ['root' => true, 'localized' => true],
            $mapping->changes($data, ['author_name' => 'Other'], ['location' => 'Brno']),
        );
    }

    public function testRejectsDecimalWithMoreFractionalPlaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CmsDomainMapping(MappedAuctionData::class))->values(new MappedAuctionData(null, '1.234', false, 1));
    }

    public function testRejectsMissingMappedColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CmsDomainMapping(MappedAuctionData::class))->changes(new MappedAuctionData(null, null, false, null), [], []);
    }
}

/**
 * Predstavuje domenova pole jedne testovaci drazby
 */
final readonly class MappedAuctionData
{
    /**
     * Nastavuje globalni a lokalizovane hodnoty
     */
    public function __construct(
        #[CmsLocalized('location')]
        public ?string $location,
        #[CmsRoot('starting_price', decimalScale: 2)]
        public ?string $startingPrice,
        #[CmsRoot('highlighted')]
        public bool $highlighted,
        #[CmsRoot('count')]
        public ?int $count,
    ) {}
}
