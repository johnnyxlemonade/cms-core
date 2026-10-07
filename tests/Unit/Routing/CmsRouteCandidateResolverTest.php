<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\CmsRoute;
use Lemonade\Cms\Routing\CmsRouteCandidateResolver;
use PHPUnit\Framework\TestCase;

/**
 * Overuje canonical suffix kandidaty bez zavislosti na databazove reservation vrstve
 */
final class CmsRouteCandidateResolverTest extends TestCase
{
    /**
     * Vybere base slug a dalsi cislovane suffixy od -2 podle obsazenych rout
     */
    public function testResolvesFirstAvailableSuffix(): void
    {
        $resolver = new CmsRouteCandidateResolver();

        self::assertSame('test', $resolver->resolve('cms.news', 10, 'aktuality', 'test', [])->slug());
        self::assertSame('test-2', $resolver->resolve('cms.news', 11, 'aktuality', 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'aktuality/test'),
        ])->slug());
        self::assertSame('test-3', $resolver->resolve('cms.news', 12, 'aktuality', 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'aktuality/test'),
            new CmsRoute(2, 'cms.news', 11, 'cs', 'aktuality/test-2'),
        ])->slug());
    }

    /**
     * Ponechava soft-deleted projection jako rezervaci a ignoruje pouze vlastni route
     */
    public function testTreatsProjectedRoutesAsReservedExceptForItsOwnTarget(): void
    {
        $resolver = new CmsRouteCandidateResolver();

        $reservation = $resolver->resolve('cms.news', 10, 'aktuality', 'test', [
            new CmsRoute(1, 'cms.news', 9, 'cs', 'aktuality/test'),
            new CmsRoute(2, 'cms.page', 20, 'cs', 'aktuality/test-2'),
            new CmsRoute(3, 'cms.news', 10, 'cs', 'aktuality/test-3'),
        ]);

        self::assertSame('test-3', $reservation->slug());
        self::assertSame('aktuality/test-3', $reservation->path());
    }

    /**
     * Zachovava databazovy limit slugu i pri pripojeni cislovaneho suffixu
     */
    public function testReservesSpaceForSuffixWithinMaximumSlugLength(): void
    {
        $resolver = new CmsRouteCandidateResolver();
        $baseSlug = str_repeat('a', 255);

        $reservation = $resolver->resolve('cms.news', 11, 'aktuality', $baseSlug, [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'aktuality/' . $baseSlug),
        ]);

        self::assertSame(255, strlen($reservation->slug()));
        self::assertSame(str_repeat('a', 253) . '-2', $reservation->slug());
    }
}
