<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

/**
 * Connects CMS lifecycle to transactions, audit and local users
 */
interface CmsContentRuntime
{
    /**
     * Runs a mutation and its audit in one transaction
     *
     * @template T
     * @param callable(CmsContentEvents):T $mutation
     * @return T
     */
    public function execute(int $actorUserId, callable $mutation): mixed;

    /**
     * Reports whether a new translation may be created for a locale
     */
    public function isEnabledLocale(string $locale): bool;

    /**
     * Reports whether ownership may be transferred to a user
     */
    public function ownerExists(int $userId): bool;
}
