<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Plugin;

use Ergonode\CategoryAdminUi\Model\CategoryTreeMappingUiProvider;
use Ergonode\CategoryConsumer\Api\CategoryStreamAvailabilityProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeSyncMetadataProviderInterface;
use Magento\Framework\UrlInterface;

class CategoryMappingConfiguration
{
    public function __construct(
        private readonly CategoryTreeSyncMetadataProviderInterface $metadata,
        private readonly CategoryStreamAvailabilityProviderInterface $availability,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function afterGetConfig(CategoryTreeMappingUiProvider $subject, array $config): array
    {
        $config['has_sync_cursor'] = (string)($this->metadata->get()['cursor'] ?? '') !== '';
        $config['synchronization_blockers'] = [
            'tree' => (string)$this->availability->getBlockingReason(),
            'data' => (string)$this->availability->getBlockingReason(data: true),
        ];
        $routes = [
            'sync' => 'ergonode/category_tree/sync',
            'sync_status' => 'ergonode/category_tree/syncStatus',
            'sync_pause' => 'ergonode/category_tree/pauseSync',
            'sync_cursor_reset' => 'ergonode/category_tree/resetSyncCursor',
        ];
        foreach ($routes as $key => $route) {
            $config['urls'][$key] = $this->url->getUrl($route);
        }
        return $config;
    }
}
