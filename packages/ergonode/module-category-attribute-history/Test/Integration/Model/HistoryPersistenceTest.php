<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Test\Integration\Model;

use Ergonode\CategoryAttributeHistory\Api\HistoryQueryInterface;
use Ergonode\CategoryAttributeHistory\Model\Persistence\HistoryWriterInterface;
use Ergonode\CategoryAttributeHistory\Model\SnapshotProvider;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Fixture\AppIsolation;
use Ergonode\CategoryAttribute\Api\AttributeMappingWriterInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class HistoryPersistenceTest extends TestCase
{
    public function testSavingAndRemovingATargetOnlyDraftRecordsItsActualChange(): void
    {
        $objects = Bootstrap::getObjectManager();
        $writer = $objects->get(AttributeMappingWriterInterface::class);
        $query = $objects->get(HistoryQueryInterface::class);
        $writer->save([], []);
        $draft = [['left' => null, 'right' => ['code' => 'description', 'type' => 'textarea']]];
        $writer->save($draft, []);
        $operation = $query->getOperations(1)['items'][0];
        $saved = $query->getState($operation['operation_id']);
        self::assertSame(1, $operation['change_count']);
        self::assertSame(['draft_added'], $saved['changes'][0]['actions']);
        self::assertSame('description', $saved['changes'][0]['code']);
        self::assertFalse($saved['changes'][0]['before']['is_draft']);
        self::assertTrue($saved['changes'][0]['after']['is_draft']);
        self::assertTrue(array_column($saved['target'], null, 'code')['description']['is_draft']);

        $writer->save($draft, []);
        self::assertSame(0, $query->getOperations(1)['items'][0]['change_count']);
        $writer->save([], []);
        $removed = $query->getState($query->getOperations(1)['items'][0]['operation_id']);
        self::assertSame(['draft_removed'], $removed['changes'][0]['actions']);
        self::assertFalse($removed['changes'][0]['after']['is_draft']);
        self::assertTrue(array_column($query->getState($operation['operation_id'])['target'], null, 'code')
            ['description']['is_draft']);
    }

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
        self::assertNull($query->getState(0));
    }

    public function testSnapshotProvidersResolveThroughRealMagentoDi(): void
    {
        $state = Bootstrap::getObjectManager()->get(SnapshotProvider::class)->getState();

        self::assertArrayHasKey('source', $state);
        self::assertArrayHasKey('target', $state);
    }

    public function testNeutralSaveIsCapturedWithoutSourceConsumerMetadata(): void
    {
        $objects = Bootstrap::getObjectManager();
        $query = $objects->get(HistoryQueryInterface::class);
        $beforeCount = $query->getOperations()['total'];
        $objects->get(AttributeMappingWriterInterface::class)->save([[
            'left' => ['code' => 'history_category_title', 'type' => 'text'],
            'right' => ['code' => 'meta_title', 'type' => 'text'],
        ]], []);
        $operations = $query->getOperations(1);
        self::assertSame($beforeCount + 1, $operations['total']);
        self::assertSame('save', $operations['items'][0]['operation_code']);
        $state = $query->getState($operations['items'][0]['operation_id']);
        $source = array_column($state['source'], null, 'code');
        self::assertSame('meta_title', $source['history_category_title']['mapped_code']);
        $targets = array_column($state['target'], null, 'code');
        self::assertSame('history_category_title', $targets['meta_title']['mapped_code']);
    }
}
