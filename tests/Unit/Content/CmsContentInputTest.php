<?php

declare(strict_types=1);

namespace Lemonade\Cms\Tests\Unit\Content;

use InvalidArgumentException;
use Lemonade\Cms\CmsPublicationState;
use Lemonade\Cms\Content\CmsContentInput;
use Lemonade\Cms\Content\CmsContentValidation;
use Lemonade\Cms\Content\CmsContentValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Overuje typy spolecneho vstupu a oddeleni publikacniho zameru
 */
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

    /**
     * Neodeslana i transportem doplnena publikace zachova puvodni stav
     */
    public function testMissingPublicationIsNotAnExplicitDraftRequest(): void
    {
        foreach ([[], ['publication_state' => null]] as $payload) {
            self::assertFalse(CmsContentInput::fromEditor($payload)->hasPublication);
        }
        self::assertTrue(CmsContentInput::fromEditor(['publication_state' => '1'])->hasPublication);
    }

    /**
     * Povinnost titulku zavisi pouze na vyslednem stavu
     */
    public function testDraftMayBeEmptyButPublishedContentRequiresTitle(): void
    {
        $input = CmsContentInput::fromEditor(['locale' => 'cs', 'title' => '  ']);
        self::assertSame([], CmsContentValidation::content($input, CmsPublicationState::Draft, 'news'));
        self::assertSame(['title' => 'news.validation.title_required'], CmsContentValidation::content($input, CmsPublicationState::Published, 'news'));
    }

    /**
     * Delky SEO a titulku plati v obou stavech a vraci vsechny chyby
     */
    public function testFilledValuesAreValidatedInBothStates(): void
    {
        $input = CmsContentInput::fromEditor(['locale' => 'cs', 'title' => str_repeat('x', 181), 'page_title' => str_repeat('x', 181), 'meta_description' => str_repeat('x', 256)]);
        foreach ([CmsPublicationState::Draft, CmsPublicationState::Published] as $state) {
            self::assertSame(['title', 'page_title', 'meta_description'], array_keys(CmsContentValidation::content($input, $state, 'news')));
        }
    }

    /**
     * Strukturovany obsah se nepretypuje na text
     */
    public function testStructuredValuesReturnEveryAffectedField(): void
    {
        try {
            CmsContentInput::fromEditor(['locale' => [], 'summary' => ['text'], 'content' => new \stdClass()]);
            self::fail('Structured text accepted');
        } catch (CmsContentValidationException $exception) {
            self::assertSame(['locale', 'summary', 'content'], array_keys($exception->errors()));
        }
    }

    /**
     * Vyplnene neplatne datum, stitky a prepinace jsou chybou i v konceptu
     */
    public function testInvalidOptionalValuesCannotBeSilentlyDiscarded(): void
    {
        foreach ([['published_at' => '2028-02-30T12:00'], ['tags' => [['nested']]], ['recommended' => 'maybe'], ['publication_state' => '']] as $payload) {
            try {
                CmsContentInput::fromEditor($payload);
                self::fail('Invalid value accepted');
            } catch (CmsContentValidationException $exception) {
                self::assertArrayHasKey(array_key_first($payload), $exception->errors());
            }
        }
    }
}
