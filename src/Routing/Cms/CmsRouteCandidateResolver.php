<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use RuntimeException;

/**
 * Chooses the first available canonical path within one locale
 */
final readonly class CmsRouteCandidateResolver
{
    private const MAXIMUM_SLUG_LENGTH = 255;

    private const MAXIMUM_CANDIDATES = 10000;

    /**
     * Finds an available slug while ignoring the target's own route
     *
     * @param list<CmsRoute> $routes
     */
    public function resolve(
        string $moduleCode,
        int $entityId,
        string $baseSlug,
        array $routes,
        int $maximumSlugLength = self::MAXIMUM_SLUG_LENGTH,
        ?callable $isReserved = null,
        string $pathPrefix = '',
    ): CmsRouteReservation {
        $occupiedPaths = [];
        foreach ($routes as $route) {
            if ($route->getModuleCode() === $moduleCode && $route->getEntityId() === $entityId) {
                continue;
            }
            $occupiedPaths[$route->getPath()] = true;
        }
        for ($suffix = 0; $suffix < self::MAXIMUM_CANDIDATES; $suffix++) {
            $slug = $this->candidate($baseSlug, $suffix, $maximumSlugLength);
            $path = $pathPrefix === '' ? $slug : $pathPrefix . '/' . $slug;
            if (!isset($occupiedPaths[$path]) && !($isReserved !== null && $isReserved($path))) {
                return new CmsRouteReservation($slug, $path);
            }
        }

        throw new RuntimeException('CMS route candidate limit was reached.');
    }

    /**
     * Builds a slug with an optional numbered suffix within the length limit
     */
    private function candidate(string $baseSlug, int $suffix, int $maximumSlugLength): string
    {
        $suffixText = $suffix === 0 ? '' : '-' . $suffix;
        $maximumBaseLength = $maximumSlugLength - strlen($suffixText);
        if ($maximumBaseLength < 1) {
            throw new RuntimeException('CMS route slug maximum length is invalid.');
        }
        $base = rtrim(substr($baseSlug, 0, $maximumBaseLength), '-');
        if ($base === '') {
            throw new RuntimeException('CMS route slug base is invalid.');
        }

        return $base . $suffixText;
    }
}
