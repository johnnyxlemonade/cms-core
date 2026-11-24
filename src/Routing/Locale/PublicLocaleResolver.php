<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Locale;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the locale and canonical form of a public CMS path
 */
final class PublicLocaleResolver
{
    private ?PublicLocaleResolution $resolution = null;

    /**
     * Receives the registry of known and enabled locales
     */
    public function __construct(
        private readonly PublicLocaleRegistryInterface $locales,
        private readonly ServerRequestInterface $request,
    ) {}

    /**
     * Chooses a route, canonical redirect or not found result
     */
    public function resolve(): PublicLocaleResolution
    {
        return $this->resolution ??= $this->resolveRequestPath($this->request->getUri()->getPath());
    }

    /**
     * Resolves canonical state from the current path and one atomic snapshot
     */
    private function resolveRequestPath(string $requestPath): PublicLocaleResolution
    {
        $snapshot = $this->locales->snapshot();
        $path = trim($requestPath, '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? null;
        $default = $snapshot->getDefaultLocale();

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
