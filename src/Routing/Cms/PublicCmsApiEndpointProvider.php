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
 * Registers the public CMS attachment API endpoint
 */
final readonly class PublicCmsApiEndpointProvider implements ApiEndpointProviderInterface
{
    /**
     * Adds the canonical attachment route to the Framework API registry
     */
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get(
            path: '/file/download/{fileId}',
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
