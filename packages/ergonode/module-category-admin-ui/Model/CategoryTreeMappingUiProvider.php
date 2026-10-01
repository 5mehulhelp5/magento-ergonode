<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Model;

use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;

class CategoryTreeMappingUiProvider
{
    public function __construct(
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly RequestInterface $request,
        private readonly CategoryTreeContextProvider $categoryTreeContextProvider,
        private readonly MappingVisibilityProviderInterface $visibilityProvider
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(bool $fresh = false): array
    {
        if ($fresh) {
            $this->categoryCacheProvider->clearCache();
            $this->magentoCategoryProvider->clearCache();
        }
        $categoryTreeId = (int)$this->request->getParam('category_tree_id', 0);
        $categoryTrees = $this->categoryTreeContextProvider->getList();
        $categoryTree = null;

        if ($categoryTreeId <= 0 && $categoryTrees !== []) {
            $categoryTreeId = (int)$categoryTrees[0]['category_tree_id'];
        }

        $treeCode = '';
        $rootCategoryId = 0;
        $magentoCategories = [];
        $error = null;

        if ($categoryTrees !== []) {
            try {
                $categoryTree = $this->categoryTreeContextProvider->getById($categoryTrees, $categoryTreeId);
                $rootCategoryId = (int)$categoryTree['root_category_id'];
                $treeCode = (string)$categoryTree['tree_code'];
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        if ($categoryTree !== null && $error === null) {
            try {
                $magentoCategories = $this->getMagentoCategories($categoryTreeId, $rootCategoryId);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        return [
            'source_issues' => $categoryTree['source_issues'] ?? [],
            'category_tree_id' => $categoryTreeId,
            'category_trees' => $categoryTrees,
            'current_category_tree' => $categoryTree,
            'tree_code' => $treeCode,
            'magento_root_id' => $rootCategoryId,
            'categories' => $categoryTree === null ? [] : $this->getErgonodeCategories($categoryTreeId),
            'magento_categories' => $magentoCategories,
            'status' => [
                'error' => $error,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getErgonodeCategories(int $categoryTreeId): array
    {
        $categories = [];
        $rows = $this->categoryCacheProvider->getRows($categoryTreeId);
        $codes = array_map(static fn (array $row): string => (string)$row['code'], $rows);
        $activeMap = $this->visibilityProvider->getActiveMap(
            'category',
            'ergo',
            $codes,
            (string)$categoryTreeId
        );

        foreach ($rows as $row) {
            $code = (string)$row['code'];
            $label = (string)$row['label'];
            $categories[] = [
                'code' => $code,
                'ergonode_category_id' => $row['ergonode_category_id'],
                'label' => $label,
                'parent_code' => $row['effective_parent_code'],
                'source_parent_code' => $row['parent_code'],
                'sort_order' => (int)$row['effective_sort_order'],
                'source_sort_order' => (int)$row['sort_order'],
                'magento_category_id' => $row['magento_category_id'],
                'sync_status' => (string)$row['sync_status'],
                'sync_message' => $row['sync_message'],
                'active' => $activeMap[$code] ?? true,
            ];
        }

        return $categories;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    private function getMagentoCategories(int $categoryTreeId, int $rootCategoryId): array
    {
        $categories = [];
        $sourceCategories = array_values($this->magentoCategoryProvider->getCategories($rootCategoryId));
        usort(
            $sourceCategories,
            static fn (array $first, array $second): int => [
                (int)$first['level'],
                (int)$first['position'],
                (int)$first['id'],
            ] <=> [
                (int)$second['level'],
                (int)$second['position'],
                (int)$second['id'],
            ]
        );
        $identifiers = array_map(static fn (array $category): string => (string)$category['id'], $sourceCategories);
        $activeMap = $this->visibilityProvider->getActiveMap(
            'category',
            'magento',
            $identifiers,
            (string)$categoryTreeId
        );
        foreach ($sourceCategories as $category) {
            $categoryId = (int)$category['id'];
            $category['active'] = $activeMap[(string)$categoryId] ?? true;
            $categories[] = $category;
        }

        return $categories;
    }
}
