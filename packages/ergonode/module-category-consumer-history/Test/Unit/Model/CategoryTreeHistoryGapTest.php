<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Test\Unit\Model;

use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use Ergonode\CategoryConsumerHistory\Model\CategoryTreeHistoryQuery;
use Ergonode\CategoryConsumerHistory\Model\ResourceModel\HistoryReader;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryTreeHistoryGapTest extends TestCase
{
    #[DataProvider('snapshots')]
    public function testSelectedSnapshotWinsAcrossAnUnrecordedMutation(
        string $entityType,
        string $action,
        ?array $after,
        ?array $laterBefore
    ): void {
        $source = ['identifier' => 'chairs', 'magento_category_id' => 12, 'label' => 'Chairs'];
        $target = ['identifier' => '12', 'label' => 'Current Magento label', 'category_code' => 'chairs'];
        $identifier = $entityType === 'source' ? 'chairs' : '12';
        $provider = $this->createStub(CategoryTreeStateProviderInterface::class);
        $provider->method('getState')->willReturn([
            'tree' => ['is_active' => true],
            'source' => [$source],
            'target' => [$target],
        ]);
        $reader = $this->createMock(HistoryReader::class);
        $reader->method('getChangeSet')->willReturn([
            'change_set_id' => 34,
            'tree_code' => 'test',
            'root_category_id' => 2,
            'root_label' => 'Root',
        ]);
        $reader->method('getOperation')->willReturn([
            'operation_id' => 34,
            'operation_code' => 'save',
            'origin' => 'admin',
            'status' => 'success',
            'started_at' => '2026-09-09 06:51:36',
        ]);
        $reader->method('getChangesAfter')->willReturn([[
            'entity_type' => $entityType,
            'entity_identifier' => $identifier,
            'before_state' => $laterBefore === null ? null : json_encode($laterBefore, JSON_THROW_ON_ERROR),
        ]]);
        $reader->expects(self::once())->method('getChanges')->with(34)->willReturn([[
            'change_id' => 1,
            'entity_type' => $entityType,
            'entity_identifier' => $identifier,
            'category_code' => 'chairs',
            'actions_json' => json_encode([$action], JSON_THROW_ON_ERROR),
            'before_state' => null,
            'after_state' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        ]]);

        $state = (new CategoryTreeHistoryQuery(
            $provider,
            $reader,
            new Json(),
            $this->synchronizationLock()
        ))->getState(3, 34);

        self::assertSame([$action], $state['changes'][0]['actions']);
        self::assertSame($after, $state['changes'][0]['after']);
        if ($after === null) {
            self::assertSame([], $state[$entityType]);
        } else {
            self::assertCount(1, $state[$entityType]);
            foreach ($after as $key => $value) {
                self::assertSame($value, $state[$entityType][0][$key]);
            }
        }
        if ($entityType === 'source') {
            self::assertSame('Current Magento label', $state['target'][0]['label']);
            self::assertNull($state['target'][0]['category_code']);
            if ($after !== null) {
                self::assertNull($state['source'][0]['magento_label']);
            }
        } else {
            self::assertSame(12, $state['source'][0]['magento_category_id']);
            self::assertSame($after['label'] ?? null, $state['source'][0]['magento_label']);
        }
    }

    public static function snapshots(): array
    {
        $connected = ['identifier' => 'chairs', 'label' => 'Chairs', 'magento_category_id' => 12];
        $disconnected = array_replace($connected, ['magento_category_id' => null]);
        $target = ['identifier' => '12', 'label' => 'Historical Magento label'];

        return [
            'disconnect before a missing reconnect' => ['source', 'disconnected', $disconnected, $connected],
            'source created before a missing recreation' => ['source', 'created', $disconnected, null],
            'source deleted before a missing recreation' => ['source', 'deleted', null, $connected],
            'target renamed before a missing rename' => [
                'target', 'renamed', $target, array_replace($target, ['label' => 'Later label']),
            ],
            'target created before a missing recreation' => ['target', 'created', $target, null],
            'target deleted before a missing recreation' => ['target', 'deleted', null, $target],
        ];
    }
    private function synchronizationLock(): CategorySynchronizationLock
    {
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        return new CategorySynchronizationLock($manager);
    }
}
