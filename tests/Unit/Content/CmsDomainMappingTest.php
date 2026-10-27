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
            'root' => ['startingPrice' => 'starting_price', 'recommended' => 'recommended', 'count' => 'count'],
            'localized' => ['location' => 'location'],
        ], (new CmsDomainMapping(MappedAuctionData::class))->columns());
    }

    public function testMapsAndComparesExactDecimalValues(): void
    {
        $mapping = new CmsDomainMapping(MappedAuctionData::class);
        $data = new MappedAuctionData('Praha', '1234567890123.5', true, null);

        self::assertSame([
            'root' => ['starting_price' => '1234567890123.50', 'recommended' => 1, 'count' => null],
            'localized' => ['location' => 'Praha'],
        ], $mapping->values($data));
        self::assertSame(
            ['root' => false, 'localized' => false],
            $mapping->changes($data, ['starting_price' => '1234567890123.50', 'recommended' => '1', 'count' => null], ['location' => 'Praha']),
        );
        self::assertSame(
            ['root' => false, 'localized' => true],
            $mapping->changes($data, ['starting_price' => '1234567890123.50', 'recommended' => '1', 'count' => null], ['location' => 'Brno']),
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
        #[CmsRoot('recommended')]
        public bool $recommended,
        #[CmsRoot('count')]
        public ?int $count,
    ) {}
}
