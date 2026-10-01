<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Provider\CategorySourceIssueProvider;
use Ergonode\Category\Model\Config\RootCategoryScopeProvider;
use Ergonode\Category\Model\Config\Source\CategoryTreeOptions;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;

class CategoryTreeContextProvider
{
    public function __construct(
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryTreeOptions $categoryTreeOptions,
        private readonly RootCategoryScopeProvider $rootCategoryScopeProvider,
        private readonly CategoryTreeSourceState $sourceState,
        private readonly CategorySourceIssueProvider $sourceIssues
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getList(): array
    {
        $treeLabels = $this->getTreeLabels();
        $rootLabels = $this->getRootLabels();

        return array_map(
            fn (array $categoryTree): array => $this->buildContext($categoryTree, $treeLabels, $rootLabels),
            $this->categoryTreeQuery->getList()
        );
    }

    /**
     * @param array<int, array<string, mixed>> $categoryTrees
     * @return array<string, mixed>
     * @throws NoSuchEntityException
     */
    public function getById(array $categoryTrees, int $categoryTreeId): array
    {
        foreach ($categoryTrees as $categoryTree) {
            if ((int)$categoryTree['category_tree_id'] === $categoryTreeId) {
                $categoryTree['source_issues'] = $this->sourceIssues->get(
                    $categoryTreeId,
                    (int)$categoryTree['root_category_id']
                );

                return $categoryTree;
            }
        }

        throw new NoSuchEntityException(__('Category Tree does not exist.'));
    }

    /**
     * @return array<string, string>
     */
    private function getTreeLabels(): array
    {
        $labels = [];
        foreach ($this->categoryTreeOptions->toOptionArray() as $option) {
            $code = (string)$option['value'];
            if ($code !== '') {
                $labels[$code] = (string)$option['label'];
            }
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function getRootLabels(): array
    {
        $labels = [];
        foreach ($this->rootCategoryScopeProvider->getRootCategories() as $root) {
            $labels[(int)$root['root_category_id']] = (string)$root['label'];
        }

        return $labels;
    }

    /**
     * @param array<string, mixed> $categoryTree
     * @param array<string, string> $treeLabels
     * @param array<int, string> $rootLabels
     * @return array<string, mixed>
     */
    private function buildContext(array $categoryTree, array $treeLabels, array $rootLabels): array
    {
        $treeCode = (string)$categoryTree['tree_code'];
        $rootCategoryId = (int)$categoryTree['root_category_id'];

        return [
            'category_tree_id' => (int)$categoryTree['category_tree_id'],
            'tree_code' => $treeCode,
            'tree_label' => $treeLabels[$treeCode] ?? $treeCode,
            'root_category_id' => $rootCategoryId,
            'root_category_label' => $rootLabels[$rootCategoryId]
                ?? (string)__('Magento Category #%1', $rootCategoryId),
            'sort_order' => (int)$categoryTree['sort_order'],
            'is_active' => (bool)$categoryTree['is_active'],
            'remove_missing' => (bool)$categoryTree['remove_missing'],
            'source_state' => $this->sourceState->get((int)$categoryTree['category_tree_id']),
        ];
    }
}
