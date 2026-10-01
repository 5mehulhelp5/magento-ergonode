<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\CategoryTree;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeValidator;

class CategoryTreeValidatorTest extends TestCase
{
    private StoreManagerInterface&Stub $storeManager;
    private CategoryTreeQuery&Stub $query;
    private CategoryTreeValidator $validator;

    protected function setUp(): void
    {
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->query = $this->createStub(CategoryTreeQuery::class);
        $this->validator = new CategoryTreeValidator($this->storeManager, $this->query);
    }

    public function testRejectsMissingRootCategory(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Magento root category is required.');

        $this->validator->validate(['root_category_id' => 0]);
    }

    public function testRejectsCategoryThatIsNotStoreGroupRoot(): void
    {
        $group = $this->createStub(GroupInterface::class);
        $group->method('getRootCategoryId')->willReturn(2);
        $this->storeManager->method('getGroups')->willReturn([$group]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Category 7 is not a Magento store group root.');

        $this->validator->validate(['root_category_id' => 7]);
    }

    public function testRejectsDuplicateRootForAnotherCategoryTree(): void
    {
        $group = $this->createStub(GroupInterface::class);
        $group->method('getRootCategoryId')->willReturn(2);
        $this->storeManager->method('getGroups')->willReturn([$group]);
        $this->query->method('findIdByRootCategoryId')->willReturn(11);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A Category Tree already exists for root category 2.');

        $this->validator->validate(['category_tree_id' => 12, 'root_category_id' => 2]);
    }

    public function testAcceptsExistingCategoryTreeKeepingItsRoot(): void
    {
        $group = $this->createStub(GroupInterface::class);
        $group->method('getRootCategoryId')->willReturn(2);
        $this->storeManager->method('getGroups')->willReturn([$group]);
        $this->query->method('findIdByRootCategoryId')->willReturn(11);

        $this->validator->validate([
            'category_tree_id' => 11,
            'root_category_id' => 2,
            'tree_code' => 'tree',
        ]);

        self::assertTrue(true);
    }
}
