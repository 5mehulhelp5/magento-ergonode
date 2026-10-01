<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Integration\Model\ResourceModel;

use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionCheckRecorderInterface;
use Ergonode\AttributeConsumer\Model\ResourceModel\AttributeDefinitionCheck;
use Ergonode\Core\Api\SynchronizationMonitorInterface;
use Ergonode\Core\Api\SynchronizationOperationExecutorInterface;
use Magento\Framework\FlagManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class AttributeDefinitionCheckTest extends TestCase
{
    public function testCheckHistoryPersistsWithoutChangingLastDetectedChange(): void
    {
        $objects = Bootstrap::getObjectManager();
        $flags = $objects->get(FlagManager::class);
        $flags->deleteFlag(AttributeDefinitionCheck::FLAG_CODE);
        $recorder = $objects->get(AttributeDefinitionCheckRecorderInterface::class);
        $reader = $objects->create(AttributeDefinitionCheck::class);
        self::assertSame('not_checked', $reader->getStatus()['status']);
        $recorder->record('running');
        self::assertNotNull($reader->getStatus()['started_at']);
        self::assertNull($reader->getStatus()['completed_at']);
        $recorder->record('initialized');
        self::assertNull($reader->getStatus()['changed_at']);
        $recorder->record('running');
        $recorder->record('changes_detected');
        $detected = $reader->getStatus()['changed_at'];
        self::assertNotNull($detected);
        $recorder->record('failed');
        self::assertSame($detected, $reader->getStatus()['changed_at']);
        self::assertNotNull($reader->getStatus()['completed_at']);
        $old = '2020-01-01 00:00:00';
        $flags->saveFlag(AttributeDefinitionCheck::FLAG_CODE, [
            'status' => 'changed', 'started_at' => $old, 'completed_at' => $old, 'changed_at' => $old,
        ]);
        $recorder->record('running');
        $recorder->record('no_changes');
        $status = $reader->getStatus();
        self::assertSame('no_changes', $status['status']);
        self::assertSame($old, $status['changed_at']);
        self::assertNotSame($old, $status['started_at']);
        self::assertNotSame($old, $status['completed_at']);
        $processes = $objects->get(SynchronizationMonitorInterface::class)->getList();
        $process = array_values(array_filter($processes, static fn (array $item): bool =>
            $item['process_code'] === 'attributeDefinitionCheck'));
        self::assertCount(1, $process);
        self::assertTrue($process[0]['monitor_only']);
        self::assertSame($status, $process[0]['observation']);
        self::assertNull($process[0]['cursor']);
        self::assertFalse($objects->get(SynchronizationOperationExecutorInterface::class)
            ->isAvailable('attributeDefinitionCheck'));
    }
}
