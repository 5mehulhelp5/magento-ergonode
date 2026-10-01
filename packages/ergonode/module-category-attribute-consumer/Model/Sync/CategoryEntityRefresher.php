<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\MappedCategoryAttributeSynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityRefresherInterface;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Exception\LocalizedException;

class CategoryEntityRefresher implements CategoryEntityRefresherInterface
{
    public function __construct(
        private readonly CategoryAttributeSourcePreparation $attributePreparation,
        private readonly CategoryFormContextProviderInterface $contextProvider,
        private readonly MappedCategoryAttributeSynchronizerInterface $attributeSynchronizer,
        private readonly CategorySynchronizationLock $synchronizationLock,
        private readonly CategoryAttributeConfigProvider $configProvider,
        private readonly CategoryDataMappingProvider $mappingProvider
    ) {
    }

    public function refresh(int $magentoCategoryId): array
    {
        return $this->synchronizationLock->execute(
            fn (): array => $this->refreshUnlocked($magentoCategoryId)
        );
    }

    /** @return array{code: string, snapshot: string, attributes: int} */
    private function refreshUnlocked(int $magentoCategoryId): array
    {
        if ($magentoCategoryId <= 0) {
            throw new LocalizedException(__('Category is required.'));
        }
        if (!$this->configProvider->isAttributeSynchronizationEnabled()) {
            throw new LocalizedException(__('Category attribute synchronization is disabled.'));
        }

        $context = $this->contextProvider->getForMagentoCategory($magentoCategoryId);
        $code = trim((string)($context['ergonode_category_code'] ?? ''));
        if ($code === '') {
            throw new LocalizedException(__('The category is not mapped with Ergonode.'));
        }

        $this->assertEligible($code, (int)$context['category_tree_id'], $magentoCategoryId);
        $this->attributePreparation->prepare();
        $stats = $this->attributeSynchronizer->synchronize($code, $magentoCategoryId);

        return [
            'code' => $code,
            'snapshot' => $stats['snapshot'],
            'attributes' => $stats['attributes'],
        ];
    }

    private function assertEligible(string $code, int $treeId, int $categoryId): void
    {
        $this->mappingProvider->clear();
        $mappings = $this->mappingProvider->getValidMappingsByCodes([$code]);
        foreach ($mappings[$code] ?? [] as $mapping) {
            if ($mapping['category_tree_id'] === $treeId && $mapping['magento_category_id'] === $categoryId) {
                return;
            }
        }

        throw new LocalizedException(__('The category is excluded from synchronization.'));
    }
}
