<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Integration\Plugin;

use Ergonode\Category\Api\CategorySnapshotRemoverInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumerHistory\Api\CategoryTreeHistoryQueryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class CategorySnapshotRemoverHistoryPluginTest extends TestCase
{
    public function testRemovalIsRecordedAndReplayedWithoutRemovingChildrenOrMagentoCategories(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, ['tree_code' => 'removal-history', 'root_category_id' => 2]);
        $treeId = (int)$connection->lastInsertId($treeTable);
        $snapshotTable = $resource->getTableName('ergonode_category_snapshot');
        foreach (['bottoms' => null, 'pants' => 'bottoms'] as $code => $parent) {
            $connection->insert($snapshotTable, [
                'category_tree_id' => $treeId,
                'category_code' => $code,
                'parent_category_code' => $parent,
                'labels_json' => '{}',
                'raw_json' => '{}',
                'content_hash' => hash('sha256', $code),
            ]);
        }
        $stateProvider = $objectManager->get(CategoryTreeStateProviderInterface::class);
        $before = $stateProvider->getState($treeId);
        $remover = $objectManager->get(CategorySnapshotRemoverInterface::class);
        $history = $objectManager->get(CategoryTreeHistoryQueryInterface::class);

        $remover->remove($treeId, 'bottoms');

        $operations = $history->getOperations($treeId);
        self::assertCount(1, $operations);
        self::assertSame('remove_snapshot', $operations[0]['operation_code']);
        self::assertSame(1, $operations[0]['summary']['deleted']);
        $operationId = $operations[0]['operation_id'];
        $state = $history->getState($treeId, $operationId);
        self::assertSame(['pants'], array_column($state['source'], 'identifier'));
        self::assertSame('bottoms', $state['source'][0]['source_parent_identifier']);
        self::assertSame($before['target'], $state['target']);
        self::assertCount(1, $state['changes']);
        self::assertSame(['deleted'], $state['changes'][0]['actions']);
        self::assertSame('bottoms', $state['changes'][0]['before']['identifier']);
        self::assertNull($state['changes'][0]['after']);

        $remover->remove($treeId, 'pants');

        self::assertCount(2, $history->getOperations($treeId));
        self::assertSame([], $history->getState($treeId)['source']);
        self::assertSame($state['source'], $history->getState($treeId, $operationId)['source']);
        self::assertSame($before['target'], $history->getState($treeId)['target']);
    }
}
