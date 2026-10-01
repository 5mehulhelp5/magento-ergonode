<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Config;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class RootCategoryScopeProvider
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     root_category_id: int,
     *     label: string,
     *     category_name: string,
     *     store_group_ids: int[],
     *     store_group_names: string[],
     *     website_ids: int[]
     * }>
     */
    public function getRootCategories(): array
    {
        $roots = [];

        foreach ($this->storeManager->getGroups(false) as $group) {
            $rootCategoryId = (int)$group->getRootCategoryId();
            if ($rootCategoryId <= 0) {
                continue;
            }

            $roots[$rootCategoryId] ??= [
                'id' => (string)$rootCategoryId,
                'root_category_id' => $rootCategoryId,
                'label' => '',
                'category_name' => '',
                'store_group_ids' => [],
                'store_group_names' => [],
                'website_ids' => [],
            ];
            $roots[$rootCategoryId]['store_group_ids'][] = (int)$group->getId();
            $roots[$rootCategoryId]['store_group_names'][] = (string)$group->getName();
            $roots[$rootCategoryId]['website_ids'][] = (int)$group->getWebsiteId();
        }

        $names = $this->loadCategoryNames(array_keys($roots));
        foreach ($roots as $rootCategoryId => &$root) {
            $root['store_group_ids'] = array_values(array_unique($root['store_group_ids']));
            $root['store_group_names'] = array_values(array_unique(array_filter($root['store_group_names'])));
            $root['website_ids'] = array_values(array_unique($root['website_ids']));
            $root['category_name'] = $names[$rootCategoryId] ?? '';
            $groupLabel = implode(', ', $root['store_group_names']);
            $root['label'] = sprintf(
                '%s (#%d)%s',
                $root['category_name'] !== '' ? $root['category_name'] : 'Root Category',
                $rootCategoryId,
                $groupLabel !== '' ? ' - ' . $groupLabel : ''
            );
        }
        unset($root);

        ksort($roots);

        return array_values($roots);
    }

    /**
     * @param int[] $categoryIds
     * @return array<int, string>
     */
    private function loadCategoryNames(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if (!$categoryIds) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID);
        $collection->addAttributeToSelect('name');
        $collection->addFieldToFilter('entity_id', ['in' => $categoryIds]);

        $names = [];
        foreach ($collection as $category) {
            $names[(int)$category->getId()] = trim((string)$category->getName());
        }

        return $names;
    }
}
