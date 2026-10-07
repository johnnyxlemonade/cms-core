<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

/**
 * Sklada lokalizovane verejne URL kanonickych CMS cest
 */
final class PublicCmsUrlBuilder
{
    /**
     * Nastavuje zdroj vychozi verejne lokalizace
     */
    public function __construct(private readonly PublicLocaleRegistryInterface $locales) {}

    /**
     * Slozi verejnou URL s prefixem pouze pro nevychozi lokalizaci
     */
    public function build(string $locale, string $path): string
    {
        $path = trim($path, '/');
        $prefix = $locale === $this->locales->defaultLocale() ? '' : '/' . $locale;

        return $prefix . ($path === '' ? '/' : '/' . $path);
    }
}
