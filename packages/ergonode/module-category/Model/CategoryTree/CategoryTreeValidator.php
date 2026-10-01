<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\CategoryTree;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class CategoryTreeValidator
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryTreeQuery $categoryTreeQuery
    ) {
    }

    /**
     * @param array<string, mixed> $categoryTree
     * @throws LocalizedException
     */
    public function validate(array $categoryTree): void
    {
        $rootCategoryId = (int)($categoryTree['root_category_id'] ?? 0);
        if ($rootCategoryId <= 0) {
            throw new LocalizedException(__('Magento root category is required.'));
        }

        $rootIds = [];
        foreach ($this->storeManager->getGroups() as $group) {
            $rootIds[] = (int)$group->getRootCategoryId();
        }
        if (!in_array($rootCategoryId, $rootIds, true)) {
            throw new LocalizedException(
                __('Category %1 is not a Magento store group root.', $rootCategoryId)
            );
        }

        $existingId = $this->categoryTreeQuery->findIdByRootCategoryId($rootCategoryId);
        $categoryTreeId = (int)($categoryTree['category_tree_id'] ?? 0);
        if ($existingId !== null && $existingId !== $categoryTreeId) {
            throw new LocalizedException(
                __('A Category Tree already exists for root category %1.', $rootCategoryId)
            );
        }

        if (trim((string)($categoryTree['tree_code'] ?? '')) === '') {
            throw new LocalizedException(__('Ergonode tree is required.'));
        }
    }
}
