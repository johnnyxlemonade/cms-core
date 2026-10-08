<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Closure;
use Lemonade\Framework\Container\ContainerInterface;

/**
 * Drzi request-scoped factory host download handleru
 */
final class PublicCmsAttachmentDownloadHandlerRegistry
{
    private ?Closure $factory = null;

    /**
     * Nastavi jedinou host factory pro verejne attachment downloady
     *
     * @param Closure(ContainerInterface):PublicCmsAttachmentDownloadHandlerInterface $factory
     */
    public function registerFactory(Closure $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * Vytvori host handler v aktivnim request scope nebo vrati null
     */
    public function handler(ContainerInterface $container): ?PublicCmsAttachmentDownloadHandlerInterface
    {
        return $this->factory === null ? null : ($this->factory)($container);
    }
}
