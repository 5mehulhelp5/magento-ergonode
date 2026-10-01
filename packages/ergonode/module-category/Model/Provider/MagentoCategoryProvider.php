<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Provider;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class MagentoCategoryProvider
{
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $categoriesCache = [];

    /** @var array<int, array<int, list<int>>> */
    private array $childrenCache = [];
    /** @var array<int, array<int, int>> */
    private array $previousCache = [];

    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     parent_id: int,
     *     label: string,
     *     path: string,
     *     level: int,
     *     position: int,
     *     url_key: string
     * }>
     * @throws LocalizedException
     */
    public function getCategories(int $rootCategoryId): array
    {
        if ($rootCategoryId <= 0) {
            return [];
        }

        if (isset($this->categoriesCache[$rootCategoryId])) {
            return $this->categoriesCache[$rootCategoryId];
        }

        $collection = $this->rootCollection($rootCategoryId);
        $collection->addAttributeToSelect(['name', 'url_key']);
        $collection->setOrder('level', 'ASC');
        $collection->setOrder('position', 'ASC');

        $categories = [];
        foreach ($collection->getItems() as $category) {
            /** @var Category $category */
            $id = (int)$category->getId();
            $categories[$id] = [
                'id' => $id,
                'parent_id' => (int)$category->getParentId(),
                'label' => (string)$category->getName(),
                'path' => (string)$category->getPath(),
                'level' => (int)$category->getLevel(),
                'position' => (int)$category->getData('position'),
                'url_key' => (string)$category->getData('url_key'),
            ];
        }

        return $this->categoriesCache[$rootCategoryId] = $categories;
    }

    /**
     * Read current membership without hydrating category models or using the operation cache.
     *
     * @return int[]
     */
    public function getIds(int $rootCategoryId): array
    {
        if ($rootCategoryId <= 0) {
            return [];
        }

        return array_map('intval', $this->rootCollection($rootCategoryId)->getAllIds());
    }

    private function rootCollection(int $rootCategoryId): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID);
        $connection = $collection->getConnection();
        $condition = $connection->quoteInto('e.entity_id = ?', $rootCategoryId)
            . ' OR '
            . $connection->quoteInto('e.path LIKE ?', '1/' . $rootCategoryId . '/%');
        $collection->getSelect()->where($condition);

        return $collection;
    }

    /**
     * @return array{
     *     id: int,
     *     parent_id: int,
     *     label: string,
     *     path: string,
     *     level: int,
     *     position: int,
     *     url_key: string
     * }|null
     * @throws LocalizedException
     */
    public function getCategory(int $categoryId, int $rootCategoryId): ?array
    {
        return $this->getCategories($rootCategoryId)[$categoryId] ?? null;
    }

    /**
     * @throws LocalizedException
     */
    public function exists(int $categoryId, int $rootCategoryId): bool
    {
        return isset($this->getCategories($rootCategoryId)[$categoryId]);
    }

    /**
     * @param array{
     *     id: int,
     *     parent_id: int,
     *     label: string,
     *     path: string,
     *     level: int,
     *     position: int,
     *     url_key: string
     * } $category
     */
    public function addOrUpdate(int $rootCategoryId, array $category): void
    {
        $this->indexChildren($rootCategoryId);
        $oldParent = (int)($this->categoriesCache[$rootCategoryId][$category['id']]['parent_id'] ?? 0);
        $this->categoriesCache[$rootCategoryId][$category['id']] = $category;
        $this->relocateInIndex($rootCategoryId, $category['id'], $oldParent, $category['parent_id']);
    }

    public function markMoved(int $rootCategoryId, int $categoryId, int $parentId, int $position): void
    {
        $categories = $this->getCategories($rootCategoryId);
        if (!isset($categories[$categoryId])) {
            return;
        }

        $this->indexChildren($rootCategoryId);
        $current = $categories[$categoryId];
        $oldParentId = (int)$current['parent_id'];
        $oldPosition = (int)$current['position'];

        $affected = array_unique([
            ...($this->childrenCache[$rootCategoryId][$oldParentId] ?? []),
            ...($this->childrenCache[$rootCategoryId][$parentId] ?? []),
        ]);
        foreach ($affected as $id) {
            $category = $categories[$id];
            if ($id === $categoryId) {
                continue;
            }

            $candidateParentId = (int)$category['parent_id'];
            $candidatePosition = (int)$category['position'];
            // Magento first closes the old gap, then opens the destination gap.
            // Apply both steps even within one parent, including duplicate positions.
            if ($candidateParentId === $oldParentId && $candidatePosition > $oldPosition) {
                $candidatePosition--;
            }
            if ($candidateParentId === $parentId && $candidatePosition >= $position) {
                $candidatePosition++;
            }
            $categories[$id]['position'] = $candidatePosition;
        }

        $categories[$categoryId]['parent_id'] = $parentId;
        $categories[$categoryId]['position'] = $position;
        $this->categoriesCache[$rootCategoryId] = $categories;
        $this->relocateInIndex($rootCategoryId, $categoryId, $oldParentId, $parentId);
    }

    public function getPreviousSiblingId(int $categoryId, int $rootCategoryId): int
    {
        $this->indexChildren($rootCategoryId);

        return $this->previousCache[$rootCategoryId][$categoryId] ?? 0;
    }

    private function indexChildren(int $rootCategoryId): void
    {
        if (isset($this->childrenCache[$rootCategoryId])) {
            return;
        }
        $this->childrenCache[$rootCategoryId] = [];
        foreach ($this->getCategories($rootCategoryId) as $category) {
            $this->childrenCache[$rootCategoryId][(int)$category['parent_id']][] = (int)$category['id'];
        }
        foreach (array_keys($this->childrenCache[$rootCategoryId]) as $parentId) {
            $this->indexPrevious($rootCategoryId, $parentId);
        }
    }

    private function relocateInIndex(int $rootCategoryId, int $categoryId, int $oldParentId, int $parentId): void
    {
        $this->childrenCache[$rootCategoryId][$oldParentId] = array_values(array_diff(
            $this->childrenCache[$rootCategoryId][$oldParentId] ?? [],
            [$categoryId]
        ));
        $this->childrenCache[$rootCategoryId][$parentId][] = $categoryId;
        foreach (array_unique([$oldParentId, $parentId]) as $affectedParent) {
            $this->indexPrevious($rootCategoryId, $affectedParent);
        }
    }

    private function indexPrevious(int $rootCategoryId, int $parentId): void
    {
        $ids = $this->childrenCache[$rootCategoryId][$parentId] ?? [];
        $categories = $this->categoriesCache[$rootCategoryId];
        usort($ids, static fn (int $left, int $right): int => [
            (int)$categories[$left]['position'], $left,
        ] <=> [(int)$categories[$right]['position'], $right]);
        $previous = 0;
        foreach ($ids as $id) {
            $this->previousCache[$rootCategoryId][$id] = $previous;
            $previous = $id;
        }
        $this->childrenCache[$rootCategoryId][$parentId] = $ids;
    }

    public function clearCache(): void
    {
        $this->categoriesCache = [];
        $this->childrenCache = [];
        $this->previousCache = [];
    }
}
