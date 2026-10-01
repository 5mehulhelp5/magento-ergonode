<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Reconciliation;

use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;

use Ergonode\Category\Api\CategoryTreeRefreshServiceInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Reconciliation\CategoryMappingContextProvider;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;

class CategoryReconciliationInputProvider
{
    public function __construct(
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryTreeRefreshServiceInterface $categoryTreeRefreshService,
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly CategoryMappingContextProvider $mappingContextProvider,
        private readonly CategorySynchronizationProgress $progress,
        private readonly CategoryCreationConfigurationProviderInterface $creationConfiguration
    ) {
    }

    /**
     * @return array{
     *     tree: array<string, mixed>,
     *     sources: array<int, array<string, mixed>>,
     *     magento: array<int, array<string, mixed>>,
     *     database_mappings: array<string, int>,
     *     fresh: array<string, mixed>
     * }
     */
    public function get(CategoryReconciliationRequestInterface $request): array
    {
        $tree = $this->categoryTreeQuery->getById($request->getCategoryTreeId());
        $fresh = [];
        if ($request->getMode() === CategoryReconciliationRequestInterface::MODE_PREVIEW) {
            if (empty($tree['is_active'])) {
                throw new LocalizedException(__('Category Tree is inactive and cannot be synchronized.'));
            }
            $this->categoryCacheProvider->clearCache();
            $this->magentoCategoryProvider->clearCache();
        } else {
            $this->creationConfiguration->get();
            $this->progress->checkpoint('fetching_tree', item: (string)$tree['tree_code']);
            $fresh = $this->categoryTreeRefreshService->refresh($request->getCategoryTreeId());
            $this->progress->checkpoint('comparing_tree', item: (string)$tree['tree_code']);
        }
        return $this->mappingContextProvider->get(
            $request->getCategoryTreeId(),
            $request->getDraftVisibility(),
            $tree
        ) + ['fresh' => $fresh];
    }
}
