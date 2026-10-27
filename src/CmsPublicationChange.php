<?php

declare(strict_types=1);

namespace Lemonade\Cms;

use DateTimeImmutable;
use InvalidArgumentException;

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
        if (!$hasRequestedState) {
            if ($currentState === CmsPublicationState::New) {
                return [CmsPublicationState::Draft, null, true];
            }

            return [$currentState, $currentPublishedAt, false];
        }

        $requestedState = CmsPublicationState::tryFrom((int) $requestedState);
        if ($requestedState === null) {
            throw new InvalidArgumentException('Invalid publication state.');
        }
        if ($requestedState === CmsPublicationState::New) {
            return [$currentState, $currentPublishedAt, false];
        }

        $publishedAtInput = trim((string) ($publishedAt ?? ''));
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
