<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Integration\Model\Source;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class CategoryMappingContractIntegrationTest extends TestCase
{
    public function testCategoryProviderReadsUnambiguousMapping(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, [
            'is_active' => 1,
            'tree_code' => 'phase-5-tree',
            'root_category_id' => 990001,
            'remove_missing' => 0,
        ]);
        $treeId = (int)$connection->lastInsertId($treeTable);
        $this->insertSnapshot($resource, $treeId, 'chairs');
        $connection->insert($resource->getTableName('ergonode_category_mapping'), [
            'category_tree_id' => $treeId,
            'ergonode_category_code' => 'chairs',
            'magento_category_id' => 42,
            'sync_status' => 'complete',
        ]);
        self::assertSame(
            [42 => 'chairs'],
            Bootstrap::getObjectManager()->get(CategoryMappingProviderInterface::class)
                ->getCategoryCodesByMagentoIds([42])
        );
    }

    public function testCategoryProviderReadsSameGlobalCodeAcrossTrees(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $treeIds = [];
        foreach (['first-tree', 'second-tree'] as $index => $treeCode) {
            $connection->insert($treeTable, [
                'is_active' => $index === 0 ? 1 : 0,
                'tree_code' => $treeCode,
                'root_category_id' => 990002 + $index,
                'remove_missing' => 0,
            ]);
            $treeIds[] = (int)$connection->lastInsertId($treeTable);
            $this->insertSnapshot($resource, $treeIds[$index], 'chairs');
        }
        foreach ($treeIds as $treeId) {
            $connection->insert($resource->getTableName('ergonode_category_mapping'), [
                'category_tree_id' => $treeId,
                'ergonode_category_code' => 'chairs',
                'magento_category_id' => 42,
                'sync_status' => 'complete',
            ]);
        }

        self::assertSame(
            [42 => 'chairs'],
            Bootstrap::getObjectManager()->get(CategoryMappingProviderInterface::class)
                ->getCategoryCodesByMagentoIds([42])
        );
    }

    public function testCategoryProviderRejectsOneMagentoIdOwnedByDifferentCodes(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $treeIds = [];
        foreach (['first-conflicting-tree', 'second-conflicting-tree'] as $index => $treeCode) {
            $connection->insert($treeTable, [
                'is_active' => 1,
                'tree_code' => $treeCode,
                'root_category_id' => 990004 + $index,
                'remove_missing' => 0,
            ]);
            $treeIds[] = (int)$connection->lastInsertId($treeTable);
        }
        foreach (['chairs', 'tables'] as $index => $code) {
            $this->insertSnapshot($resource, $treeIds[$index], $code);
            $connection->insert($resource->getTableName('ergonode_category_mapping'), [
                'category_tree_id' => $treeIds[$index],
                'ergonode_category_code' => $code,
                'magento_category_id' => 44,
                'sync_status' => 'complete',
            ]);
        }

        $this->expectException(LocalizedException::class);
        Bootstrap::getObjectManager()->get(CategoryMappingProviderInterface::class)
            ->getCategoryCodesByMagentoIds([44]);
    }

    private function insertSnapshot(ResourceConnection $resource, int $treeId, string $code): void
    {
        $resource->getConnection()->insert($resource->getTableName('ergonode_category_snapshot'), [
            'category_tree_id' => $treeId,
            'category_code' => $code,
            'sort_order' => 0,
            'labels_json' => '{}',
            'content_hash' => hash('sha256', $treeId . ':' . $code),
            'raw_json' => '{}',
        ]);
    }
}
