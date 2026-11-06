<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use Lemonade\Cms\CmsPublicationState;

/**
 * Uklada pridruzene domenove hodnoty uvnitr zamcene CMS transakce
 */
interface CmsContentValueWriter
{
    /**
     * Overi a zamkne definici pred zalozenim root zaznamu ve stejne transakci
     */
    public function beforeCreateDraft(string $locale): void;

    /**
     * Inicializuje hodnoty noveho konceptu pred zapisem created auditu
     */
    public function createDraft(int $id, string $locale): void;

    /**
     * Validuje i nezmeneny publikovany obsah a vrati, zda hodnoty zmenil
     */
    public function prepare(int $id, string $locale, CmsPublicationState $state, object $domain): bool;

    /**
     * Ulozi pripravene hodnoty po vytvoreni jazykove mutace ve stejne transakci
     */
    public function save(int $id, string $locale): void;
}
