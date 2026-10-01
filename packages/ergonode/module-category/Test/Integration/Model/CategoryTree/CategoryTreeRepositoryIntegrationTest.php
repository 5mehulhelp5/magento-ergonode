<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Integration\Model\CategoryTree;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;

#[AppIsolation(true), DbIsolation(true)]
class CategoryTreeRepositoryIntegrationTest extends TestCase
{
    public function testCrudPersistsCompleteNonDestructivePolicy(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $rootId = $this->rootCategoryId($objectManager->get(StoreManagerInterface::class));
        $repository = $objectManager->get(CategoryTreeRepository::class);
        $query = $objectManager->get(CategoryTreeQuery::class);

        $categoryTreeId = $repository->save([
            'is_active' => true,
            'tree_code' => 'tree-pl',
            'root_category_id' => $rootId,
            'remove_missing' => true,
        ]);

        $categoryTree = $query->getById($categoryTreeId);
        self::assertArrayNotHasKey('code', $categoryTree);
        self::assertSame('tree-pl', $categoryTree['tree_code']);
        self::assertSame($rootId, $categoryTree['root_category_id']);
        self::assertSame(0, $categoryTree['sort_order']);
        self::assertTrue($categoryTree['remove_missing']);
        self::assertArrayNotHasKey('delete_missing_magento', $categoryTree);

        $repository->deleteById($categoryTreeId);
        self::assertSame([], $query->getList());
    }

    public function testRejectsDuplicateRoot(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $rootId = $this->rootCategoryId($objectManager->get(StoreManagerInterface::class));
        $repository = $objectManager->get(CategoryTreeRepository::class);
        $repository->save($this->categoryTree('first', $rootId));

        $this->expectException(LocalizedException::class);
        $repository->save($this->categoryTree('second', $rootId));
    }

    public function testKeepsSavedScopeImmutableWhileUpdatingOtherSettings(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $rootId = $this->rootCategoryId($objectManager->get(StoreManagerInterface::class));
        $repository = $objectManager->get(CategoryTreeRepository::class);
        $query = $objectManager->get(CategoryTreeQuery::class);
        $categoryTreeId = $repository->save($this->categoryTree('immutable', $rootId));

        $repository->save([
            'category_tree_id' => $categoryTreeId,
            'is_active' => false,
            'remove_missing' => true,
        ]);

        $categoryTree = $query->getById($categoryTreeId);
        self::assertFalse($categoryTree['is_active']);
        self::assertSame('tree-immutable', $categoryTree['tree_code']);
        self::assertSame($rootId, $categoryTree['root_category_id']);
        self::assertTrue($categoryTree['remove_missing']);

        $this->expectException(LocalizedException::class);
        $repository->save([
            'category_tree_id' => $categoryTreeId,
            'tree_code' => 'other-tree',
            'root_category_id' => $rootId,
        ]);
    }

    public function testReordersEveryConfiguredTree(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resourceConnection = $objectManager->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $table = $resourceConnection->getTableName('ergonode_category_tree');
        $connection->insertMultiple($table, [
            [
                'tree_code' => 'first-tree',
                'root_category_id' => 900001,
                'sort_order' => 0,
            ],
            [
                'tree_code' => 'second-tree',
                'root_category_id' => 900002,
                'sort_order' => 1,
            ],
        ]);
        $repository = $objectManager->get(CategoryTreeRepository::class);
        $query = $objectManager->get(CategoryTreeQuery::class);
        $categoryTrees = $query->getList();
        $categoryTreeIds = array_column($categoryTrees, 'category_tree_id');

        $repository->reorder(array_reverse($categoryTreeIds));

        $reordered = $query->getList();
        self::assertSame(array_reverse($categoryTreeIds), array_column($reordered, 'category_tree_id'));
        self::assertSame([0, 1], array_column($reordered, 'sort_order'));
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryTree(string $code, int $rootId): array
    {
        return [
            'is_active' => true,
            'tree_code' => 'tree-' . $code,
            'root_category_id' => $rootId,
            'remove_missing' => false,
        ];
    }

    private function rootCategoryId(StoreManagerInterface $storeManager): int
    {
        foreach ($storeManager->getGroups() as $group) {
            $rootId = (int)$group->getRootCategoryId();
            if ($rootId > 0) {
                return $rootId;
            }
        }

        self::fail('Magento integration fixture has no store group root.');
    }
}
