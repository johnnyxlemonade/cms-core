<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use Lemonade\Cms\Http\Controller\PublicCmsAttachmentDownloadController;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Routing\ControllerAction;

/**
 * Registruje verejny API endpoint pro stazeni CMS prilohy
 */
final readonly class PublicCmsApiEndpointProvider implements ApiEndpointProviderInterface
{
    /**
     * Pripojuje canonical public attachment route do Framework API registry
     */
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get(
            path: '/file/{file}/download',
            controllerAction: ControllerAction::for(
                controllerClass: PublicCmsAttachmentDownloadController::class,
                method: 'download',
            ),
            name: 'api.file.download',
            summary: 'Public CMS attachment download',
            description: 'Returns a CMS attachment through the configured host download handler.',
            access: ApiAccess::Public,
            metadata: new ApiEndpointMetadata(
                tags: ['CMS', 'Files'],
                responseContentType: 'application/octet-stream',
            ),
        );
    }
}
