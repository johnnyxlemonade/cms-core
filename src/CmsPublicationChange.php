<?php

declare(strict_types=1);

namespace Lemonade\Cms;

use DateTimeImmutable;
use InvalidArgumentException;
use Lemonade\Cms\Content\CmsContentValidation;

/**
 * Resolves the publication state and timestamp for one translation
 */
final class CmsPublicationChange
{
    /**
     * Prevents construction of a stateless publication resolver
     */
    private function __construct() {}

    /**
     * Returns the resolved state, timestamp and whether publication changed
     *
     * Raw editor values remain mixed so invalid transport values raise domain errors
     *
     * @return array{CmsPublicationState,string|null,bool}
     */
    public static function resolve(
        bool $hasRequestedState,
        mixed $requestedState,
        mixed $publishedAt,
        int $currentState,
        mixed $currentPublishedAt,
        string $now,
    ): array {
        $currentPublicationState = CmsPublicationState::tryFrom($currentState)
            ?? CmsPublicationState::New;
        $normalizedCurrentPublishedAt = trim((string) $currentPublishedAt);
        $normalizedCurrentPublishedAt = $normalizedCurrentPublishedAt === '' ? null : $normalizedCurrentPublishedAt;
        if ($publishedAt !== null && (!is_string($publishedAt) || str_contains($publishedAt, "\0"))) {
            throw new InvalidArgumentException('Invalid publication date.');
        }
        $publishedAtInput = trim($publishedAt ?? '');
        if ($publishedAtInput !== '' && !CmsContentValidation::validDate($publishedAtInput)) {
            throw new InvalidArgumentException('Invalid publication date.');
        }
        if (!$hasRequestedState || $requestedState === null) {
            if ($currentPublicationState === CmsPublicationState::New) {
                return [CmsPublicationState::Draft, null, true];
            }

            return [$currentPublicationState, $normalizedCurrentPublishedAt, false];
        }

        if (!in_array($requestedState, [
            CmsPublicationState::Draft->value,
            CmsPublicationState::Published->value,
            (string) CmsPublicationState::Draft->value,
            (string) CmsPublicationState::Published->value,
        ], true)) {
            throw new InvalidArgumentException('Invalid publication state.');
        }
        $requestedPublicationState = CmsPublicationState::from((int) $requestedState);

        $resolvedPublishedAt = null;
        if ($requestedPublicationState === CmsPublicationState::Published) {
            if ($publishedAtInput === '') {
                $resolvedPublishedAt = $currentPublicationState === CmsPublicationState::Published
                    ? $normalizedCurrentPublishedAt ?? $now
                    : $now;
            } else {
                $datetime = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $publishedAtInput);
                if ($datetime === false || $datetime->format('Y-m-d\\TH:i') !== $publishedAtInput) {
                    throw new InvalidArgumentException('Invalid publication date.');
                }
                $resolvedPublishedAt = $datetime->format('Y-m-d H:i:s');
            }
        }

        $publicationChanged = $requestedPublicationState !== $currentPublicationState
            || $resolvedPublishedAt !== $normalizedCurrentPublishedAt;

        return [
            $requestedPublicationState,
            $resolvedPublishedAt,
            $publicationChanged,
        ];
    }
}
