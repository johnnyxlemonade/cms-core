<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\CmsRoute;
use Lemonade\Cms\Routing\Cms\CmsRouteCandidateResolver;
use PHPUnit\Framework\TestCase;

/**
 * Overuje canonical suffix kandidaty bez zavislosti na databazove reservation vrstve
 */
final class CmsRouteCandidateResolverTest extends TestCase
{
    /**
     * Vybere base slug a dalsi cislovane suffixy od -1 podle obsazenych rout
     */
    public function testResolvesFirstAvailableSuffix(): void
    {
        $resolver = new CmsRouteCandidateResolver();

        self::assertSame('test', $resolver->resolve('cms.news', 10, 'test', [])->slug());
        self::assertSame('test-1', $resolver->resolve('cms.news', 11, 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'test'),
        ])->slug());
        self::assertSame('test-1', $resolver->resolve('cms.news', 12, 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'test'),
            new CmsRoute(2, 'cms.news', 11, 'cs', 'test-2'),
        ])->slug());
        self::assertSame('test-2', $resolver->resolve('cms.news', 12, 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'test'),
            new CmsRoute(2, 'cms.news', 11, 'cs', 'test-1'),
        ])->slug());
    }

    /**
     * Ponechava soft-deleted projection jako rezervaci a ignoruje pouze vlastni route
     */
    public function testTreatsProjectedRoutesAsReservedExceptForItsOwnTarget(): void
    {
        $resolver = new CmsRouteCandidateResolver();

        $reservation = $resolver->resolve('cms.news', 10, 'test', [
            new CmsRoute(1, 'cms.news', 9, 'cs', 'test'),
            new CmsRoute(2, 'cms.page', 20, 'cs', 'test-1'),
            new CmsRoute(3, 'cms.news', 10, 'cs', 'test-2'),
        ]);

        self::assertSame('test-2', $reservation->slug());
        self::assertSame('test-2', $reservation->path());
    }

    /**
     * Zachovava databazovy limit slugu i pri pripojeni cislovaneho suffixu
     */
    public function testReservesSpaceForSuffixWithinMaximumSlugLength(): void
    {
        $resolver = new CmsRouteCandidateResolver();
        $baseSlug = str_repeat('a', 180);

        $reservation = $resolver->resolve('cms.news', 11, $baseSlug, [
            new CmsRoute(1, 'cms.news', 10, 'cs', $baseSlug),
        ], maximumSlugLength: 180);

        self::assertSame(180, strlen($reservation->slug()));
        self::assertSame(str_repeat('a', 178) . '-1', $reservation->slug());
    }
}
