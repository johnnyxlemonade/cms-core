<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Content;

use InvalidArgumentException;
use Lemonade\Cms\Content\CmsContentInput;
use PHPUnit\Framework\TestCase;

final class CmsContentInputTest extends TestCase
{
    public function testDisplaySettingsCannotWriteAuditOwnership(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CmsContentInput(
            locale: 'cs',
            title: 'Article',
            pageTitle: null,
            summary: null,
            content: null,
            metaDescription: null,
            tags: null,
            hasPublication: false,
            publicationState: null,
            publishedAt: null,
            displaySettings: ['owner_id' => 9],
        );
    }
}
