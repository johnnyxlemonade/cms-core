<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Routing\Locale\PublicLocaleResolution;

/**
 * Builds localized public URLs from canonical CMS paths
 */
final readonly class PublicCmsUrlBuilder
{
    /**
     * Prefixes the path only for a nondefault locale
     */
    public function build(PublicLocaleResolution $resolution, string $locale, string $path): string
    {
        $path = trim($path, '/');
        $prefix = $locale === $resolution->getDefaultLocale() ? '' : '/' . $locale;

        return $prefix . ($path === '' ? '/' : '/' . $path);
    }
}
