<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Unit\Model;

use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryQuery;
use Ergonode\CategoryConsumerHistory\Model\ResourceModel\HistoryReader;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryTreeHistoryQueryTest extends TestCase
{
    public function testReturnsAKeysetPageWithTotalAndNextCursor(): void
    {
        $stateProvider = $this->createStub(CategoryTreeStateProviderInterface::class);
        $reader = $this->createMock(HistoryReader::class);
        $reader->expects(self::once())
            ->method('getOperations')
            ->with(7, 3, 90)
            ->willReturn([
                $this->operationRow(89),
                array_replace($this->operationRow(88), ['operation_code' => 'synchronize_data']),
                $this->operationRow(87),
            ]);
        $reader->expects(self::once())
            ->method('countOperations')
            ->with(7)
            ->willReturn(5);

        $page = (new CategoryTreeHistoryQuery(
            $stateProvider,
            $reader,
            new Json(),
            $this->synchronizationLock()
        ))
            ->getOperationsPage(7, 2, 90);

        self::assertSame(5, $page['total']);
        self::assertSame(2, $page['page_size']);
        self::assertTrue($page['has_more']);
        self::assertSame(88, $page['next_before_id']);
        self::assertSame([89, 88], array_column($page['items'], 'operation_id'));
        self::assertSame('treeStream', $page['items'][0]['mode']);
        self::assertSame('categoryStream', $page['items'][1]['mode']);
    }

    public function testReconstructsSelectedStateByUndoingLaterChangesNewestFirst(): void
    {
        $stateProvider = $this->createStub(CategoryTreeStateProviderInterface::class);
        $stateProvider->method('getState')->willReturn([
            'tree' => [
                'category_tree_id' => 7,
                'tree_code' => 'current-code',
                'root_category_id' => 2,
                'root_label' => 'Current Root',
                'is_active' => true,
            ],
            'source' => [
                $this->source('chairs', 'future-parent'),
                $this->source('future-category', null),
            ],
            'target' => [],
        ]);
        $reader = $this->createStub(HistoryReader::class);
        $reader->method('getChangeSet')->willReturn([
            'change_set_id' => 100,
            'operation_id' => 10,
            'category_tree_id' => 7,
            'tree_code' => 'historical-code',
            'root_category_id' => 2,
            'root_label' => 'Historical Root',
        ]);
        $reader->method('getOperation')->willReturn([
            'operation_id' => 10,
            'operation_code' => 'save',
            'origin' => 'admin',
            'status' => 'success',
            'actor_name' => 'Admin User',
            'started_at' => '2026-09-06 10:00:00',
            'finished_at' => '2026-09-06 10:00:01',
        ]);
        $reader->method('getChangesAfter')->willReturn([
            [
                'entity_type' => 'source',
                'entity_identifier' => 'chairs',
                'before_state' => json_encode($this->source('chairs', 'selected-parent'), JSON_THROW_ON_ERROR),
            ],
            [
                'entity_type' => 'source',
                'entity_identifier' => 'future-category',
                'before_state' => null,
            ],
        ]);
        $reader->method('getChanges')->willReturn([[
            'change_id' => 101,
            'entity_type' => 'source',
            'entity_identifier' => 'chairs',
            'category_code' => 'chairs',
            'actions_json' => '["moved"]',
            'before_state' => json_encode($this->source('chairs', 'original-parent'), JSON_THROW_ON_ERROR),
            'after_state' => json_encode($this->source('chairs', 'selected-parent'), JSON_THROW_ON_ERROR),
        ]]);

        $state = (new CategoryTreeHistoryQuery(
            $stateProvider,
            $reader,
            new Json(),
            $this->synchronizationLock()
        ))->getState(7, 10);

        self::assertCount(1, $state['source']);
        self::assertSame('selected-parent', $state['source'][0]['parent_identifier']);
        self::assertSame('historical-code', $state['tree']['tree_code']);
        self::assertSame('Historical Root', $state['tree']['root_label']);
        self::assertSame(['moved'], $state['changes'][0]['actions']);
        self::assertSame(10, $state['operation']['operation_id']);
    }

    /** @return array<string, int|string|bool|null> */
    private function source(string $identifier, ?string $parent): array
    {
        return [
            'identifier' => $identifier,
            'label' => ucfirst(str_replace('-', ' ', $identifier)),
            'parent_identifier' => $parent,
            'source_parent_identifier' => $parent,
            'sort_order' => 1,
            'source_sort_order' => 1,
            'magento_category_id' => null,
            'magento_label' => null,
            'active' => true,
        ];
    }

    /** @return array<string, int|string|null> */
    private function operationRow(int $operationId): array
    {
        return [
            'operation_id' => $operationId,
            'change_set_id' => $operationId + 100,
            'operation_code' => 'synchronize',
            'origin' => 'cron',
            'status' => 'success',
            'actor_name' => null,
            'started_at' => '2026-09-06 10:00:00',
            'finished_at' => '2026-09-06 10:00:01',
            'set_summary_json' => '{"changes":2,"categories":1}',
            'operation_summary_json' => '{"trees":1}',
        ];
    }
    private function synchronizationLock(): CategorySynchronizationLock
    {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        return new CategorySynchronizationLock($manager);
    }
}
