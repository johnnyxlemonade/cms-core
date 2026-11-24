<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit;

use Lemonade\Cms\CmsPublicationState;
use PHPUnit\Framework\TestCase;

/**
 * Verifies stable database values for shared publication lifecycle
 */
final class CmsPublicationStateTest extends TestCase
{
    /**
     * Publication lifecycle uses stable numeric values for content translations
     */
    public function testDefinesStablePublicationStates(): void
    {
        self::assertSame(0, CmsPublicationState::New->value);
        self::assertSame(1, CmsPublicationState::Draft->value);
        self::assertSame(2, CmsPublicationState::Published->value);
    }

    public function testEditorOffersOnlyDraftAndPublishedAndDisplaysNewAsDraft(): void
    {
        self::assertSame([
            '1' => 'admin.status.draft',
            '2' => 'admin.status.published',
        ], CmsPublicationState::editorOptionLabelKeys());
        self::assertSame('1', CmsPublicationState::New->getEditorValue());
        self::assertSame('1', CmsPublicationState::Draft->getEditorValue());
        self::assertSame('2', CmsPublicationState::Published->getEditorValue());
    }
}
