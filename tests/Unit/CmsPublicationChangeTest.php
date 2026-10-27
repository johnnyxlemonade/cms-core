<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit;

use InvalidArgumentException;
use Lemonade\Cms\CmsPublicationChange;
use Lemonade\Cms\CmsPublicationState;
use PHPUnit\Framework\TestCase;

/**
 * Overuje zmeny publikace a zachovani existujicich hodnot
 */
final class CmsPublicationChangeTest extends TestCase
{
    /**
     * @dataProvider changes
     * @param array{CmsPublicationState,string|null,bool} $expected
     */
    public function testPublicationChange(
        bool $present,
        mixed $requested,
        mixed $inputDate,
        mixed $current,
        mixed $currentDate,
        array $expected,
    ): void {
        self::assertSame($expected, CmsPublicationChange::resolve($present, $requested, $inputDate, (int) ($current ?? CmsPublicationState::New->value), $currentDate, '2026-10-01 09:00:00'));
    }

    /**
     * Poskytuje skutecne stavy publikace a jejich ocekavane zmeny
     *
     * @return iterable<string,array{bool,mixed,mixed,mixed,mixed,array{CmsPublicationState,string|null,bool}}>
     */
    public static function changes(): iterable
    {
        yield 'missing state' => [false, null, 'invalid', 2, '2026-10-01 10:00:00', [CmsPublicationState::Published, '2026-10-01 10:00:00', false]];
        yield 'new retains publication' => [true, 0, '', 2, '2026-10-01 10:00:00', [CmsPublicationState::Published, '2026-10-01 10:00:00', false]];
        yield 'scheduled' => [true, 2, '2027-03-01T10:30', 1, null, [CmsPublicationState::Published, '2027-03-01 10:30:00', true]];
        yield 'same date' => [true, 2, '2027-03-01T10:30', 2, '2027-03-01 10:30:00', [CmsPublicationState::Published, '2027-03-01 10:30:00', false]];
        yield 'retained date' => [true, 2, '', 2, '2027-03-01 10:30:00', [CmsPublicationState::Published, '2027-03-01 10:30:00', false]];
        yield 'unpublish' => [true, 1, '', 2, '2027-03-01 10:30:00', [CmsPublicationState::Draft, null, true]];
        yield 'draft no op' => [true, 1, 'invalid', 1, null, [CmsPublicationState::Draft, null, false]];
        yield 'first ordinary save promotes new to draft' => [false, null, '', null, null, [CmsPublicationState::Draft, null, true]];
        yield 'explicit draft promotes existing new' => [true, 1, '', 0, null, [CmsPublicationState::Draft, null, true]];
        yield 'publish existing new' => [true, 2, '', 0, null, [CmsPublicationState::Published, '2026-10-01 09:00:00', true]];
        yield 're-edit draft without publication input' => [false, null, '', 1, null, [CmsPublicationState::Draft, null, false]];
    }

    public function testPublicationWithoutDateUsesCurrentTime(): void
    {
        $now = '2026-10-01 09:00:00';

        self::assertSame(
            [CmsPublicationState::Published, $now, true],
            CmsPublicationChange::resolve(true, 2, '', 1, null, $now),
        );
        self::assertSame(
            [CmsPublicationState::Published, $now, true],
            CmsPublicationChange::resolve(true, 2, '', 2, null, $now),
        );
        self::assertSame(
            [CmsPublicationState::Published, '2026-10-01 08:00:00', false],
            CmsPublicationChange::resolve(true, 2, '', 2, '2026-10-01 08:00:00', $now),
        );
    }

    /**
     * @dataProvider invalidValues
     */
    public function testInvalidPublicationValuesAreRejected(mixed $state, string $date): void
    {
        $this->expectException(InvalidArgumentException::class);
        CmsPublicationChange::resolve(true, $state, $date, 1, null, '2026-10-01 09:00:00');
    }

    /**
     * @return iterable<string,array{mixed,string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'invalid state' => [3, ''];
        yield 'invalid date' => [2, '2026-02-30T12:00'];
        yield 'invalid format' => [2, '2026-10-01 10:00:00'];
    }
}
