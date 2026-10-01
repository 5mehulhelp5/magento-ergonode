<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\ResourceModel;

use Ergonode\CategoryConsumer\Api\CategoryPositionWriterInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\Exception\NoSuchEntityException;

class CategoryPositionWriter implements CategoryPositionWriterInterface
{
    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryResource $categoryResource
    ) {
    }

    public function move(int $categoryId, int $parentId, int $previousCategoryId): void
    {
        // Load fresh positions: moving a sibling changes already loaded category objects.
        $category = $this->categoryFactory->create();
        $this->categoryResource->load($category, $categoryId);
        if (!$category->getId()) {
            throw NoSuchEntityException::singleField('id', $categoryId);
        }
        // CategoryManagement rewrites null and some valid sibling IDs to the last child.
        // The model preserves insert-after semantics and Magento's events/index/cache updates.
        $category->move($parentId, $previousCategoryId);
    }
}
