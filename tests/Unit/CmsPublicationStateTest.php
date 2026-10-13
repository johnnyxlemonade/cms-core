<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit;

use Lemonade\Cms\CmsPublicationState;
use PHPUnit\Framework\TestCase;

/**
 * Overuje stabilni databazove hodnoty shared publication lifecycle
 */
final class CmsPublicationStateTest extends TestCase
{
    /**
     * Publication lifecycle ma stabilni ciselne hodnoty pro content mutace
     */
    public function testDefinesStablePublicationStates(): void
    {
        self::assertSame(0, CmsPublicationState::New->value);
        self::assertSame(1, CmsPublicationState::Draft->value);
        self::assertSame(2, CmsPublicationState::Published->value);
    }
}
