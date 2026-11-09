<?php

declare(strict_types=1);

namespace Lemonade\Cms;

use DateTimeImmutable;
use InvalidArgumentException;
use Lemonade\Cms\Content\CmsContentValidation;

/**
 * Vyhodnocuje zmenu publikacniho stavu jedne lokalizovane mutace
 */
final class CmsPublicationChange
{
    /**
     * Brani vytvareni instance stateless funkce
     */
    private function __construct() {}

    /**
     * Vrati stav, datum a informaci o zmene publikace
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
        $currentState = CmsPublicationState::tryFrom($currentState)
            ?? CmsPublicationState::New;
        $currentPublishedAt = trim((string) $currentPublishedAt);
        $currentPublishedAt = $currentPublishedAt === '' ? null : $currentPublishedAt;
        if ($publishedAt !== null && (!is_string($publishedAt) || str_contains($publishedAt, "\0"))) {
            throw new InvalidArgumentException('Invalid publication date.');
        }
        $publishedAtInput = trim($publishedAt ?? '');
        if ($publishedAtInput !== '' && !CmsContentValidation::validDate($publishedAtInput)) {
            throw new InvalidArgumentException('Invalid publication date.');
        }
        if (!$hasRequestedState || $requestedState === null) {
            if ($currentState === CmsPublicationState::New) {
                return [CmsPublicationState::Draft, null, true];
            }

            return [$currentState, $currentPublishedAt, false];
        }

        if (!in_array($requestedState, [1, 2, '1', '2'], true)) {
            throw new InvalidArgumentException('Invalid publication state.');
        }
        $requestedState = CmsPublicationState::from((int) $requestedState);

        $resolvedPublishedAt = null;
        if ($requestedState === CmsPublicationState::Published) {
            if ($publishedAtInput === '') {
                $resolvedPublishedAt = $currentState === CmsPublicationState::Published
                    ? $currentPublishedAt ?? $now
                    : $now;
            } else {
                $datetime = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $publishedAtInput);
                if ($datetime === false || $datetime->format('Y-m-d\\TH:i') !== $publishedAtInput) {
                    throw new InvalidArgumentException('Invalid publication date.');
                }
                $resolvedPublishedAt = $datetime->format('Y-m-d H:i:s');
            }
        }

        return [
            $requestedState,
            $resolvedPublishedAt,
            $requestedState !== $currentState || $resolvedPublishedAt !== $currentPublishedAt,
        ];
    }
}
