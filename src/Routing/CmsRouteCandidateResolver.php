<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

use RuntimeException;

/**
 * Vybira prvni volnou canonical cestu z projection rout jednoho module prefixu
 */
final class CmsRouteCandidateResolver
{
    private const MAXIMUM_SLUG_LENGTH = 255;

    /**
     * Najde prvni volny slug se suffixem od -2 a ignoruje vlastni route targetu
     *
     * @param list<CmsRoute> $routes
     */
    public function resolve(
        string $moduleCode,
        int $entityId,
        string $prefix,
        string $baseSlug,
        array $routes,
    ): CmsRouteReservation {
        $occupiedPaths = [];
        foreach ($routes as $route) {
            if ($route->moduleCode() === $moduleCode && $route->entityId() === $entityId) {
                continue;
            }
            $occupiedPaths[$route->path()] = true;
        }

        for ($suffix = 1; ; $suffix++) {
            $slug = $this->candidate($baseSlug, $suffix);
            $path = $prefix . '/' . $slug;
            if (!isset($occupiedPaths[$path])) {
                return new CmsRouteReservation($slug, $path);
            }
        }
    }

    /**
     * Sestavi base slug nebo jeho prvni cislovany suffix v ramci databazove delky
     */
    private function candidate(string $baseSlug, int $suffix): string
    {
        $suffixText = $suffix === 1 ? '' : '-' . $suffix;
        $maximumBaseLength = self::MAXIMUM_SLUG_LENGTH - strlen($suffixText);
        $base = rtrim(substr($baseSlug, 0, $maximumBaseLength), '-');
        if ($base === '') {
            throw new RuntimeException('CMS route slug base is invalid.');
        }

        return $base . $suffixText;
    }
}
