<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

/**
 * Vyjadruje routu, canonical redirect nebo neplatnou verejnou lokalizaci
 */
final readonly class PublicLocaleResolution
{
    /**
     * Nastavuje vysledek bez kombinovani redirectu a routy
     */
    private function __construct(
        private string $defaultLocale,
        private ?string $locale,
        private ?string $path,
        private ?string $redirectTo,
    ) {}

    /**
     * Vytvori vysledek routovani pro platnou lokalizaci
     */
    public static function route(string $defaultLocale, string $locale, string $path): self
    {
        return new self($defaultLocale, $locale, $path, null);
    }

    /**
     * Vytvori canonical redirect pro redundantni locale prefix
     */
    public static function redirect(string $defaultLocale, string $to): self
    {
        return new self($defaultLocale, null, null, $to);
    }

    /**
     * Vytvori vysledek pro neznamou nebo vypnutou lokalizaci
     */
    public static function notFound(string $defaultLocale): self
    {
        return new self($defaultLocale, null, null, null);
    }

    /**
     * Vrati vychozi lokalizaci bez canonical URL prefixu
     */
    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Vrati lokalizaci pouzitou pro vyhledani routy
     */
    public function locale(): ?string
    {
        return $this->locale;
    }

    /**
     * Vrati cestu bez locale prefixu
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * Vrati cil canonical redirectu, pokud je nutny
     */
    public function redirectTo(): ?string
    {
        return $this->redirectTo;
    }
}
