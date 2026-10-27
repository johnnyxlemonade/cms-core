<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

/**
 * Propojuje CMS lifecycle s transakcemi, auditem a lokalnimi uzivateli
 */
interface CmsContentRuntime
{
    /**
     * Provede mutaci a audit ve spolecne transakci
     *
     * @template T
     * @param callable(CmsContentEvents):T $mutation
     * @return T
     */
    public function execute(int $actorUserId, callable $mutation): mixed;

    /**
     * Rozhodne, zda lze zalozit novou mutaci pro zadany jazyk
     */
    public function isEnabledLocale(string $locale): bool;

    /**
     * Rozhodne, zda lze uzivateli predat vlastnictvi
     */
    public function ownerExists(int $userId): bool;
}
