<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

use RuntimeException;

/**
 * Popisuje atomicky stav systemovych jazyku pro verejne routovani
 */
final readonly class PublicLocaleSnapshot
{
    /**
     * Nastavuje vychozi, aktivni a zname verejne lokalizace
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
     * Vytvori snapshot z canonical radku systemovych jazyku
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
     * Vrati vychozi lokalizaci bez URL prefixu
     */
    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Overi aktivni nevychozi lokalizaci pro locale prefix
     */
    public function isEnabledNonDefault(string $locale): bool
    {
        return $locale !== $this->defaultLocale && in_array($locale, $this->enabledLocales, true);
    }

    /**
     * Overi, zda kod odpovida znamemu systemovemu jazyku
     */
    public function isKnownLocale(string $locale): bool
    {
        return in_array($locale, $this->knownLocales, true);
    }
}
