<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Urcuje lokalizaci a canonical podobu verejne CMS cesty
 */
final class PublicLocaleResolver
{
    private ?PublicLocaleResolution $resolution = null;

    /**
     * Nastavuje registry znamych a aktivnich lokalizaci
     */
    public function __construct(
        private readonly PublicLocaleRegistryInterface $locales,
        private readonly ServerRequestInterface $request,
    ) {}

    /**
     * Rozhodne mezi routou, canonical redirectem a not found
     */
    public function resolve(): PublicLocaleResolution
    {
        return $this->resolution ??= $this->resolveRequestPath($this->request->getUri()->getPath());
    }

    /**
     * Rozhodne canonical stav z aktualni cesty a jednoho atomickeho snapshotu
     */
    private function resolveRequestPath(string $requestPath): PublicLocaleResolution
    {
        $snapshot = $this->locales->snapshot();
        $path = trim($requestPath, '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? null;
        $default = $snapshot->defaultLocale();

        if ($first === $default) {
            array_shift($segments);

            return PublicLocaleResolution::redirect($default, '/' . implode('/', $segments));
        }
        if ($first !== null && $snapshot->isEnabledNonDefault($first)) {
            array_shift($segments);

            return PublicLocaleResolution::route($default, $first, implode('/', $segments));
        }
        if ($first !== null && $snapshot->isKnownLocale($first)) {
            return PublicLocaleResolution::notFound($default);
        }

        return PublicLocaleResolution::route($default, $default, $path);
    }
}
