<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Integration\Model;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumerHistory\Api\CategoryTreeHistoryQueryInterface;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['name' => 'History Chairs'], as: 'chairs')]
class CategoryTreeHistoryCaptureIntegrationTest extends TestCase
{
    public function testCapturesDisconnectReconnectDisconnectWithWarmCaches(): void
    {
        $objects = Bootstrap::getObjectManager();
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('chairs')->getId();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, ['tree_code' => 'fresh-history', 'root_category_id' => 2]);
        $treeId = (int)$connection->lastInsertId($treeTable);
        $connection->insert($resource->getTableName('ergonode_category_snapshot'), [
            'category_tree_id' => $treeId,
            'category_code' => 'chairs',
            'labels_json' => '{}',
            'raw_json' => '{}',
            'content_hash' => hash('sha256', 'chairs'),
        ]);
        $mappingWriter = $objects->get(CategoryMappingWriter::class);
        $mappingWriter->updateMagentoLink($treeId, 'chairs', $categoryId);
        $provider = $objects->get(CategoryTreeStateProviderInterface::class);
        $capture = $objects->get(CategoryTreeHistoryCapture::class);
        $history = $objects->get(CategoryTreeHistoryQueryInterface::class);
        $operationIds = [];

        foreach ([null, $categoryId, null] as $targetId) {
            $provider->getState($treeId);
            $capture->execute(
                $targetId === null ? 'save' : 'synchronize',
                [$treeId],
                static function () use ($mappingWriter, $treeId, $targetId): void {
                    if ($targetId === null) {
                        $mappingWriter->saveLayout($treeId, 'chairs', null, null, null);
                    } else {
                        $mappingWriter->updateMagentoLink($treeId, 'chairs', $targetId);
                    }
                },
                static fn (): array => ['status' => 'success', 'summary' => []]
            );
            $operation = $history->getOperations($treeId, 1)[0];
            $action = $targetId === null ? 'disconnected' : 'connected';
            self::assertSame(1, $operation['summary'][$action] ?? 0);
            self::assertSame(1, $operation['change_count']);
            $operationIds[] = $operation['operation_id'];
        }

        foreach ($operationIds as $index => $operationId) {
            $state = $history->getState($treeId, $operationId);
            $targetId = $index === 1 ? $categoryId : null;
            self::assertSame($targetId, $state['source'][0]['magento_category_id']);
            $targets = array_column($state['target'], null, 'identifier');
            self::assertSame($targetId === null ? null : 'chairs', $targets[$categoryId]['category_code']);
        }
    }

    public function testReadsMagentoChangesAfterTheTargetCacheWasPopulated(): void
    {
        $objects = Bootstrap::getObjectManager();
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('chairs')->getId();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, ['tree_code' => 'fresh-target-history', 'root_category_id' => 2]);
        $treeId = (int)$connection->lastInsertId($treeTable);
        $provider = $objects->get(CategoryTreeStateProviderInterface::class);
        $before = array_column($provider->getState($treeId)['target'], null, 'identifier');
        $newPosition = $before[$categoryId]['sort_order'] + 7;

        $connection->update(
            $resource->getTableName('catalog_category_entity'),
            ['position' => $newPosition],
            ['entity_id = ?' => $categoryId]
        );

        $after = array_column($provider->getState($treeId)['target'], null, 'identifier');
        self::assertSame($newPosition, $after[$categoryId]['sort_order']);
    }
}
