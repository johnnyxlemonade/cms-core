<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing;

use Lemonade\Framework\View\ViewRendererInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Obsluhuje verejny seznam obsahu modulu na jeho lokalizovanem prefixu
 */
interface PublicCmsCollectionHandlerInterface
{
    /**
     * Vytvori odpoved pro collection route modulu v pozadovanem locale
     */
    public function handleCollection(string $locale, string $prefix, ViewRendererInterface $views): ?ResponseInterface;
}
