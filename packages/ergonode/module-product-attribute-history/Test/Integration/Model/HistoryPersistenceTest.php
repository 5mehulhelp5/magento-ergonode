<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistory\Test\Integration\Model;

use Ergonode\ProductAttributeHistory\Api\HistoryQueryInterface;
use Ergonode\ProductAttributeHistory\Model\Persistence\HistoryWriterInterface;
use Ergonode\ProductAttributeHistory\Model\SnapshotProvider;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\Framework\App\ResourceConnection;
use Ergonode\ProductAttribute\Model\Mapping\AttributeMappingSaver;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class HistoryPersistenceTest extends TestCase
{
    public function testStatesRemainIndependentAndPaginationUsesOperationCursor(): void
    {
        $objects = Bootstrap::getObjectManager();
        $writer = $objects->get(HistoryWriterInterface::class);
        $query = $objects->get(HistoryQueryInterface::class);
        $beforeTotal = $query->getOperations()['total'];
        $attribute = [
            'code' => 'history_fixture', 'label' => 'Original label', 'type' => 'text',
            'scope' => 'global', 'active' => true, 'mapped_code' => null,
        ];
        $operation = [
            'operation_code' => 'save', 'status' => 'success', 'origin' => 'cli',
            'actor_id' => null, 'actor_name' => null, 'started_at' => '2026-09-07 10:00:00',
            'finished_at' => '2026-09-07 10:00:01', 'change_count' => 1,
            'state' => ['source' => [$attribute], 'target' => []],
            'changes' => [[
                'side' => 'source', 'code' => 'history_fixture', 'actions' => ['created'],
                'before' => null, 'after' => $attribute,
            ]],
        ];
        $writer->save($operation);
        $firstId = $query->getOperations(1)['items'][0]['operation_id'];
        $operation['state']['source'][0]['label'] = 'Later label';
        $writer->save($operation);
        $latest = $query->getOperations(1);

        self::assertSame($beforeTotal + 2, $latest['total']);
        self::assertTrue($latest['has_more']);
        self::assertSame(
            $firstId,
            $query->getOperations(1, $latest['items'][0]['operation_id'])['items'][0]['operation_id']
        );
        self::assertSame('Original label', $query->getState($firstId)['source'][0]['label']);
        self::assertSame('Later label', $query->getState($latest['items'][0]['operation_id'])['source'][0]['label']);
        self::assertNull($query->getState($firstId)['options']);
        self::assertNull($query->getState(0));
    }

    public function testSnapshotProvidersResolveThroughRealMagentoDi(): void
    {
        $state = Bootstrap::getObjectManager()->get(SnapshotProvider::class)->getState();

        self::assertArrayHasKey('source', $state);
        self::assertArrayHasKey('target', $state);
    }

    public function testNeutralSaveRecordsMappingAndRepeatedSnapshotsReadFreshMetadata(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $table = $resource->getTableName('ergonode_attribute');
        $code = 'history_neutral_attr';
        $resource->getConnection()->insert($table, [
            'code' => $code, 'type' => 'text', 'scope' => 'global',
            'labels_json' => '{"en_US":"Before label"}', 'parameters_json' => '{}',
            'content_hash' => hash('sha256', $code),
        ]);
        $snapshot = $objects->get(SnapshotProvider::class);
        $beforeState = $snapshot->getState();
        $beforeSource = array_column($beforeState['source'], null, 'code');
        self::assertNull($beforeSource[$code]['mapped_code']);
        $query = $objects->get(HistoryQueryInterface::class);
        $beforeCount = $query->getOperations()['total'];

        $objects->get(AttributeMappingSaver::class)->saveAdditions([[
            'left' => ['code' => $code, 'type' => 'text'],
            'right' => ['code' => 'name', 'type' => 'text'],
        ]]);

        $operations = $query->getOperations(1);
        self::assertSame($beforeCount + 1, $operations['total']);
        self::assertSame('auto_map', $operations['items'][0]['operation_code']);
        $operationId = $operations['items'][0]['operation_id'];
        $historical = array_column($query->getState($operationId)['source'], null, 'code');
        self::assertSame('name', $historical[$code]['mapped_code']);

        $resource->getConnection()->update(
            $table,
            ['labels_json' => '{"en_US":"After label"}'],
            ['code = ?' => $code]
        );
        $fresh = array_column($snapshot->getState()['source'], null, 'code');
        self::assertSame('After label', $fresh[$code]['label']);
        self::assertSame('name', $fresh[$code]['mapped_code']);
        self::assertSame('Before label', $historical[$code]['label']);
        self::assertSame($historical, array_column($query->getState($operationId)['source'], null, 'code'));
    }
}
