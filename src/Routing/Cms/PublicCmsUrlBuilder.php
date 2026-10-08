<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;

/**
 * Sklada lokalizovane verejne URL kanonickych CMS cest
 */
final class PublicCmsUrlBuilder
{
    /**
     * Slozi verejnou URL s prefixem pouze pro nevychozi lokalizaci z request resolution
     */
    public function build(PublicLocaleResolution $resolution, string $locale, string $path): string
    {
        $path = trim($path, '/');
        $prefix = $locale === $resolution->defaultLocale() ? '' : '/' . $locale;

        return $prefix . ($path === '' ? '/' : '/' . $path);
    }
}
