<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

use RuntimeException;

/**
 * Represents an atomic system language snapshot for public routing
 */
final readonly class PublicLocaleSnapshot
{
    /**
     * Receives the default, enabled and known public locales
     *
     * @param list<string> $enabledLocales
     * @param list<string> $knownLocales
     */
    public function __construct(
        private string $defaultLocale,
        private array $enabledLocales,
        private array $knownLocales,
    ) {}

    /**
     * Creates a snapshot from canonical system language rows
     *
     * @param list<array{code:mixed,enabled:mixed,is_default:mixed}> $rows
     */
    public static function fromRows(array $rows): self
    {
        $defaultLocale = null;
        $enabledLocales = [];
        $knownLocales = [];

        foreach ($rows as $row) {
            $locale = trim((string) $row['code']);
            if ($locale === '') {
                continue;
            }

            $knownLocales[] = $locale;
            if ((bool) $row['enabled']) {
                $enabledLocales[] = $locale;
            }
            if ((bool) $row['enabled'] && (bool) $row['is_default']) {
                if ($defaultLocale !== null) {
                    throw new RuntimeException('Public locale snapshot has multiple enabled default locales.');
                }

                $defaultLocale = $locale;
            }
        }

        if ($defaultLocale === null) {
            throw new RuntimeException('Public locale snapshot has no enabled default locale.');
        }

        return new self($defaultLocale, $enabledLocales, $knownLocales);
    }

    /**
     * Returns the default locale without a URL prefix
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Checks an enabled nondefault locale for a locale prefix
     */
    public function isEnabledNonDefault(string $locale): bool
    {
        return $locale !== $this->defaultLocale && in_array($locale, $this->enabledLocales, true);
    }

    /**
     * Checks whether a code is a known system language
     */
    public function isKnownLocale(string $locale): bool
    {
        return in_array($locale, $this->knownLocales, true);
    }
}
