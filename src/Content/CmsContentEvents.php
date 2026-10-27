<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

/**
 * Zaznamenava auditni udalosti jedne transakcni mutace obsahu
 */
interface CmsContentEvents
{
    /**
     * Zaznamena skutecnou zmenu konkretniho CMS zaznamu
     *
     * @param array<string,mixed> $payload
     */
    public function record(string $event, string $moduleCode, string $entityType, int $entityId, array $payload = []): void;

    /**
     * Oznaci transakci bez skutecne zmeny
     */
    public function noOp(): void;
}
