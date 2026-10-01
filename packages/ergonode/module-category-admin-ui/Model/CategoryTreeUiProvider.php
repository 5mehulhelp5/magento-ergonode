<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Model;

use Ergonode\Category\Model\Config\RootCategoryScopeProvider;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;

class CategoryTreeUiProvider
{
    public function __construct(
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly RootCategoryScopeProvider $rootCategoryScopeProvider
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getRootOptions(): array
    {
        $options = [];
        foreach ($this->rootCategoryScopeProvider->getRootCategories() as $root) {
            $rootCategoryId = (int)$root['root_category_id'];
            $options[$rootCategoryId] = [
                'value' => (string)$root['root_category_id'],
                'label' => (string)$root['label'],
            ];
        }

        foreach ($this->categoryTreeQuery->getList() as $categoryTree) {
            $rootCategoryId = (int)$categoryTree['root_category_id'];
            if ($rootCategoryId > 0 && !isset($options[$rootCategoryId])) {
                $options[$rootCategoryId] = [
                    'value' => (string)$rootCategoryId,
                    'label' => 'Root #' . $rootCategoryId,
                ];
            }
        }
        ksort($options);

        return array_values($options);
    }
}
