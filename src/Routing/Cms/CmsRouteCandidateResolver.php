<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use RuntimeException;

/**
 * Vybira prvni volnou canonical cestu ze slugu v ramci jednoho locale
 */
final class CmsRouteCandidateResolver
{
    private const MAXIMUM_SLUG_LENGTH = 255;

    /**
     * Najde prvni volny slug se suffixem od -1 a ignoruje vlastni route targetu
     *
     * @param list<CmsRoute> $routes
     */
    public function resolve(
        string $moduleCode,
        int $entityId,
        string $baseSlug,
        array $routes,
        int $maximumSlugLength = self::MAXIMUM_SLUG_LENGTH,
    ): CmsRouteReservation {
        $occupiedPaths = [];
        foreach ($routes as $route) {
            if ($route->moduleCode() === $moduleCode && $route->entityId() === $entityId) {
                continue;
            }
            $occupiedPaths[$route->path()] = true;
        }
        for ($suffix = 0; ; $suffix++) {
            $slug = $this->candidate($baseSlug, $suffix, $maximumSlugLength);
            $path = $slug;
            if (!isset($occupiedPaths[$path])) {
                return new CmsRouteReservation($slug, $path);
            }
        }
    }

    /**
     * Sestavi base slug nebo jeho prvni cislovany suffix v ramci databazove delky
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
