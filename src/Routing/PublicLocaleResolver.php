<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

/**
 * Urcuje lokalizaci a canonical podobu verejne CMS cesty
 */
final class PublicLocaleResolver
{
    /**
     * Nastavuje registry znamych a aktivnich lokalizaci
     */
    public function __construct(private readonly PublicLocaleRegistryInterface $locales) {}

    /**
     * Rozhodne mezi routou, canonical redirectem a not found
     */
    public function resolve(string $requestPath): PublicLocaleResolution
    {
        $path = trim($requestPath, '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? null;
        $default = $this->locales->defaultLocale();

        if ($first === $default) {
            array_shift($segments);

            return PublicLocaleResolution::redirect('/' . implode('/', $segments));
        }
        if ($first !== null && $this->locales->isEnabledNonDefault($first)) {
            array_shift($segments);

            return PublicLocaleResolution::route($first, implode('/', $segments));
        }
        if ($first !== null && $this->locales->isKnownLocale($first)) {
            return PublicLocaleResolution::notFound();
        }

        return PublicLocaleResolution::route($default, $path);
    }
}
