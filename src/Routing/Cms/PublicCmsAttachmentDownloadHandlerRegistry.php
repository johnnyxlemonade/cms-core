<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Closure;
use Lemonade\Framework\Container\ContainerInterface;

/**
 * Holds the host download handler factory for the request scope
 */
final class PublicCmsAttachmentDownloadHandlerRegistry
{
    private ?Closure $factory = null;

    /**
     * Registers the one host factory for public attachment downloads
     *
     * @param Closure(ContainerInterface):PublicCmsAttachmentDownloadHandlerInterface $factory
     */
    public function registerFactory(Closure $factory): void
    {
        $this->factory = $factory;
    }

    /**
     * Creates the host handler in the active request scope or returns null
     */
    public function handler(ContainerInterface $container): ?PublicCmsAttachmentDownloadHandlerInterface
    {
        return $this->factory === null ? null : ($this->factory)($container);
    }
}
