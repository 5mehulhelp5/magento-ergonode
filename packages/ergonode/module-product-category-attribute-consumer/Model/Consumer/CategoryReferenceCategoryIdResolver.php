<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttributeConsumer\Model\Consumer;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\ProductConsumer\Exception\DependencyUnavailableException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class CategoryReferenceCategoryIdResolver
{
    /** @var array<int, array<string, int>> */
    private array $resolvedIds = [];

    public function __construct(
        private readonly CategoryMappingProviderInterface $categoryMappingProvider,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function resolve(string $categoryCode, int $storeId): int
    {
        $categoryCode = trim($categoryCode);
        if ($categoryCode === '') {
            throw new LocalizedException(__('Ergonode category code must not be empty.'));
        }
        $rootCategoryId = $this->rootCategoryId($storeId);
        if (isset($this->resolvedIds[$rootCategoryId][$categoryCode])) {
            return $this->resolvedIds[$rootCategoryId][$categoryCode];
        }
        $resolved = $this->categoryMappingProvider->getMagentoCategoryIdsByErgonodeCodes(
            [$categoryCode],
            $rootCategoryId
        );
        $categoryIds = $resolved[$categoryCode] ?? [];
        if ($categoryIds === []) {
            throw new DependencyUnavailableException(__(
                'Ergonode category "%1" is not mapped below Magento root category ID %2 yet.',
                $categoryCode,
                $rootCategoryId
            ));
        }
        if (count($categoryIds) !== 1) {
            throw new LocalizedException(__(
                'Ergonode category "%1" has ambiguous mappings below Magento root category ID %2.',
                $categoryCode,
                $rootCategoryId
            ));
        }

        return $this->resolvedIds[$rootCategoryId][$categoryCode] = (int)reset($categoryIds);
    }

    public function resetResolutionScope(): void
    {
        $this->resolvedIds = [];
    }

    private function rootCategoryId(int $storeId): int
    {
        $store = $storeId > 0
            ? $this->storeManager->getStore($storeId)
            : $this->storeManager->getDefaultStoreView();
        if ($store === null) {
            throw new DependencyUnavailableException(__('Magento default store view is not configured.'));
        }
        $group = $this->storeManager->getGroup((int)$store->getStoreGroupId());
        $rootCategoryId = (int)$group->getRootCategoryId();
        if ($rootCategoryId <= 0) {
            throw new DependencyUnavailableException(__('Magento store category root is not configured.'));
        }

        return $rootCategoryId;
    }
}
