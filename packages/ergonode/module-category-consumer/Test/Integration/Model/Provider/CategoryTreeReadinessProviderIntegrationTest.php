<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Provider;

use Ergonode\CategoryConsumer\Api\CategoryTreeReadinessProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class CategoryTreeReadinessProviderIntegrationTest extends TestCase
{
    public function testReturnsActiveTreeAndExistingRoot(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_category_tree');
        $connection->insert($table, [
            'is_active' => 1,
            'tree_code' => 'readiness-sync-tree',
            'root_category_id' => $this->rootCategoryId($objectManager->get(StoreManagerInterface::class)),
            'remove_missing' => 0,
        ]);
        $categoryTreeId = (int)$connection->lastInsertId($table);
        $provider = $objectManager->get(CategoryTreeReadinessProviderInterface::class);

        $trees = $provider->getActiveTrees('readiness-sync-tree');

        self::assertCount(1, $trees);
        self::assertSame($categoryTreeId, $trees[0]['category_tree_id']);
        self::assertTrue($trees[0]['root_exists']);
    }

    private function rootCategoryId(StoreManagerInterface $storeManager): int
    {
        foreach ($storeManager->getGroups() as $group) {
            $rootCategoryId = (int)$group->getRootCategoryId();
            if ($rootCategoryId > 0) {
                return $rootCategoryId;
            }
        }

        self::fail('Magento integration fixture has no store group root.');
    }
}
