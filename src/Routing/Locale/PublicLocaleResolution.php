<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

/**
 * Represents a route, canonical redirect or invalid public locale
 */
final readonly class PublicLocaleResolution
{
    /**
     * Receives a result without combining redirects and routes
     */
    private function __construct(
        private string $defaultLocale,
        private ?string $locale,
        private ?string $path,
        private ?string $redirectTo,
    ) {}

    /**
     * Creates a route result for a valid locale
     */
    public static function route(string $defaultLocale, string $locale, string $path): self
    {
        return new self($defaultLocale, $locale, $path, null);
    }

    /**
     * Creates a canonical redirect for a redundant locale prefix
     */
    public static function redirect(string $defaultLocale, string $to): self
    {
        return new self($defaultLocale, null, null, $to);
    }

    /**
     * Creates a result for an unknown or disabled locale
     */
    public static function notFound(string $defaultLocale): self
    {
        return new self($defaultLocale, null, null, null);
    }

    /**
     * Returns the default locale without a canonical URL prefix
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Returns the locale used to find the route
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Returns the path without a locale prefix
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Returns the canonical redirect target when needed
     */
    public function getRedirectTo(): ?string
    {
        return $this->redirectTo;
    }
}
