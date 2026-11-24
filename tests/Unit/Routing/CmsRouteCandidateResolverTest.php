<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Routing;

use Lemonade\Cms\Routing\Cms\CmsRoute;
use Lemonade\Cms\Routing\Cms\CmsRouteCandidateResolver;
use PHPUnit\Framework\TestCase;

/**
 * Verifies canonical suffix candidates without database reservations
 */
final class CmsRouteCandidateResolverTest extends TestCase
{
    /**
     * Chooses the base slug and numbered suffixes from occupied routes
     */
    public function testResolvesFirstAvailableSuffix(): void
    {
        $resolver = new CmsRouteCandidateResolver();

        self::assertSame('test', $resolver->resolve('cms.news', 10, 'test', [])->getSlug());
        self::assertSame('test-1', $resolver->resolve('cms.news', 11, 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'test'),
        ])->getSlug());
        self::assertSame('test-1', $resolver->resolve('cms.news', 12, 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'test'),
            new CmsRoute(2, 'cms.news', 11, 'cs', 'test-2'),
        ])->getSlug());
        self::assertSame('test-2', $resolver->resolve('cms.news', 12, 'test', [
            new CmsRoute(1, 'cms.news', 10, 'cs', 'test'),
            new CmsRoute(2, 'cms.news', 11, 'cs', 'test-1'),
        ])->getSlug());
    }

    /**
     * Keeps deleted routes reserved and ignores only the target's own route
     */
    public function testTreatsProjectedRoutesAsReservedExceptForItsOwnTarget(): void
    {
        $resolver = new CmsRouteCandidateResolver();

        $reservation = $resolver->resolve('cms.news', 10, 'test', [
            new CmsRoute(1, 'cms.news', 9, 'cs', 'test'),
            new CmsRoute(2, 'cms.page', 20, 'cs', 'test-1'),
            new CmsRoute(3, 'cms.news', 10, 'cs', 'test-2'),
        ]);

        self::assertSame('test-2', $reservation->getSlug());
        self::assertSame('test-2', $reservation->getPath());
    }

    /**
     * Preserves the slug length limit after adding a numbered suffix
     */
    public function testReservesSpaceForSuffixWithinMaximumSlugLength(): void
    {
        $resolver = new CmsRouteCandidateResolver();
        $baseSlug = str_repeat('a', 180);

        $reservation = $resolver->resolve('cms.news', 11, $baseSlug, [
            new CmsRoute(1, 'cms.news', 10, 'cs', $baseSlug),
        ], maximumSlugLength: 180);

        self::assertSame(180, strlen($reservation->getSlug()));
        self::assertSame(str_repeat('a', 178) . '-1', $reservation->getSlug());
    }
}
