<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

/**
 * Vyjadruje routu, canonical redirect nebo neplatnou verejnou lokalizaci
 */
final readonly class PublicLocaleResolution
{
    /**
     * Nastavuje vysledek bez kombinovani redirectu a routy
     */
    private function __construct(
        private ?string $locale,
        private ?string $path,
        private ?string $redirectTo,
    ) {}

    /**
     * Vytvori vysledek routovani pro platnou lokalizaci
     */
    public static function route(string $locale, string $path): self
    {
        return new self($locale, $path, null);
    }

    /**
     * Vytvori canonical redirect pro redundantni locale prefix
     */
    public static function redirect(string $to): self
    {
        return new self(null, null, $to);
    }

    /**
     * Vytvori vysledek pro neznamou nebo vypnutou lokalizaci
     */
    public static function notFound(): self
    {
        return new self(null, null, null);
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
