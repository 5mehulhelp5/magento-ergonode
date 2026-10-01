<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Integration\Model\ResourceModel;

use Ergonode\CategoryConsumerHistory\Model\ResourceModel\HistoryReader;
use Ergonode\CategoryConsumerHistory\Model\ResourceModel\HistoryWriter;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryRecorder;
use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryQuery;

#[DbIsolation(true)]
class HistoryWriterTest extends TestCase
{
    public function testDataRenameIsUndoneForCategoryUntouchedBySelectedOperation(): void
    {
        $objects = Bootstrap::getObjectManager();
        $recorder = $objects->get(CategoryTreeHistoryRecorder::class);
        $reader = $objects->get(HistoryReader::class);
        $treeId = 900003;
        $initial = [
            'tree' => [
                'category_tree_id' => $treeId, 'tree_code' => 'data-replay',
                'root_category_id' => 2, 'root_label' => 'Root', 'is_active' => true,
            ],
            'source' => [],
            'target' => [
                ['identifier' => '12', 'label' => 'X before', 'sort_order' => 1],
                ['identifier' => '13', 'label' => 'Y before', 'sort_order' => 2],
            ],
        ];
        $selected = $initial;
        $selected['target'][1]['label'] = 'Y after';
        $current = $selected;
        $current['target'][0]['label'] = 'X after data stream';
        $recorder->record('save', 'success', '2026-09-18 10:00:00', [], [$treeId => $initial], [$treeId => $selected]);
        $operationId = (int)$reader->getOperations($treeId, 1)[0]['operation_id'];
        $recorder->record(
            'synchronize_data',
            'success',
            '2026-09-18 10:01:00',
            [],
            [$treeId => $selected],
            [$treeId => $current]
        );
        $provider = $this->createStub(CategoryTreeStateProviderInterface::class);
        $provider->method('getState')->willReturn($current);
        $query = $objects->create(CategoryTreeHistoryQuery::class, ['stateProvider' => $provider]);

        $replayed = $query->getState($treeId, $operationId);

        self::assertSame(['X before', 'Y after'], array_column($replayed['target'], 'label'));
        self::assertCount(1, $replayed['changes']);
        self::assertSame('13', $replayed['changes'][0]['entity_identifier']);
        $latest = $query->getOperations($treeId, 1)[0];
        self::assertSame('categoryStream', $latest['mode']);
        self::assertSame('categoryStream', $query->getState($treeId, $latest['operation_id'])['operation']['mode']);
    }

    public function testPersistsOneGroupedOperationWithReversibleTreeChanges(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $writer = $objectManager->get(HistoryWriter::class);
        $reader = $objectManager->get(HistoryReader::class);
        $writer->save(
            'synchronize',
            'success',
            '2026-09-06 10:00:00',
            ['origin' => 'cli', 'actor_id' => null, 'actor_name' => null],
            ['trees' => 1],
            [[
                'tree' => [
                    'category_tree_id' => 900001,
                    'tree_code' => 'integration-tree',
                    'root_category_id' => 2,
                    'root_label' => 'Default Category',
                    'is_active' => true,
                ],
                'before_hash' => str_repeat('a', 64),
                'after_hash' => str_repeat('b', 64),
                'summary' => ['changes' => 1, 'categories' => 1, 'moved' => 1],
                'changes' => [[
                    'entity_type' => 'source',
                    'entity_identifier' => 'chairs',
                    'category_code' => 'chairs',
                    'actions' => ['moved'],
                    'before' => ['identifier' => 'chairs', 'parent_identifier' => 'old'],
                    'after' => ['identifier' => 'chairs', 'parent_identifier' => 'new'],
                ]],
            ]]
        );

        $operations = $reader->getOperations(900001, 10);
        self::assertCount(1, $operations);
        self::assertSame('synchronize', $operations[0]['operation_code']);
        self::assertSame(1, $reader->countOperations(900001));
        self::assertSame([], $reader->getOperations(900001, 10, (int)$operations[0]['operation_id']));
        $changes = $reader->getChanges((int)$operations[0]['change_set_id']);
        self::assertCount(1, $changes);
        self::assertSame('["moved"]', $changes[0]['actions_json']);
        self::assertStringContainsString('"parent_identifier":"old"', (string)$changes[0]['before_state']);
    }

    public function testKeysetReplayCrossesPagesAndOperationsWithoutSkippingChanges(): void
    {
        $objects = Bootstrap::getObjectManager();
        $writer = $objects->get(HistoryWriter::class);
        $reader = $objects->get(HistoryReader::class);
        $context = ['origin' => 'cli', 'actor_id' => null, 'actor_name' => null];
        $first = $writer->start('synchronize', '2026-09-06 10:00:00', $context);
        $expected = [];
        foreach ([501, 505] as $count) {
            $id = $writer->start('synchronize', '2026-09-06 10:00:00', $context);
            $changes = [];
            for ($index = 0; $index < $count; $index++) {
                $code = $id . '-' . $index;
                $expected[] = $code;
                $changes[] = [
                    'entity_type' => 'source', 'entity_identifier' => $code, 'category_code' => $code,
                    'actions' => ['renamed'], 'before' => ['label' => 'Before'], 'after' => ['label' => 'After'],
                ];
            }
            $writer->append($id, [[
                'tree' => ['category_tree_id' => 900002, 'tree_code' => 'replay',
                    'root_category_id' => 2, 'root_label' => 'Root'],
                'before_hash' => str_repeat('a', 64), 'after_hash' => str_repeat('b', 64),
                'summary' => ['changes' => $count], 'changes' => $changes,
            ]]);
            $writer->finish($id, 'success', ['trees' => 1]);
        }
        $actual = [];
        foreach ($reader->getChangesAfter(900002, $first) as $change) {
            $actual[] = $change['entity_identifier'];
        }
        self::assertSame(array_reverse($expected), $actual);
        self::assertCount(1006, $actual);
    }
}
