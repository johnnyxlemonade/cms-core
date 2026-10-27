<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit;

use InvalidArgumentException;
use Lemonade\Cms\CmsTagNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Overuje poradi, hranici delky a vstupni podoby lokalizovanych stitku
 */
final class CmsTagNormalizerTest extends TestCase
{
    public function testNormalizesTextAndPreservesOrder(): void
    {
        self::assertSame(
            ['Praha', 'Česká Lípa', 'praha'],
            CmsTagNormalizer::normalize(" Praha, Česká Lípa\r\nPraha,, praha \n"),
        );
    }

    public function testArrayValuesAreNotSplitAndNonStringsAreIgnored(): void
    {
        self::assertSame(
            ['Praha, Brno', 'Ostrava'],
            CmsTagNormalizer::normalize([' Praha, Brno ', '', null, 12, 'Ostrava', 'Ostrava']),
        );
    }

    public function testAcceptsExactly255Characters(): void
    {
        $tag = str_repeat('ě', 255);
        self::assertSame([$tag], CmsTagNormalizer::normalize([$tag]));
    }

    public function testRejectsTagLongerThan255Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CmsTagNormalizer::normalize([str_repeat('ě', 256)]);
    }
}
