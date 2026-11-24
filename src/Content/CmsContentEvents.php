<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

/**
 * Records audit events for one transactional content mutation
 */
interface CmsContentEvents
{
    /**
     * Records an actual change to a specific CMS record
     *
     * @param array<string,mixed> $payload
     */
    public function record(string $event, string $moduleCode, string $entityType, int $entityId, array $payload = []): void;

    /**
     * Marks a transaction without an actual change
     */
    public function noOp(): void;
}
