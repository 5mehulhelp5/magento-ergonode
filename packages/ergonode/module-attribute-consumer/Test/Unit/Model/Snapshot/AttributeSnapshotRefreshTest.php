<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Snapshot;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\AttributeConsumer\Model\Port\AttributeDefinitionSnapshotInterface;
use Ergonode\AttributeConsumer\Model\Snapshot\AttributeSnapshotRefresh;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class AttributeSnapshotRefreshTest extends TestCase
{
    public function testRefreshReadsOnlyRequestedSnapshotPageAndReleasesSharedLock(): void
    {
        $importer = $this->createMock(AttributeDefinitionSnapshotInterface::class);
        $importer->expects(self::once())->method('page')->with('browser-cursor', 25)
            ->willReturn(['has_more' => true, 'cursor' => 'next']);
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::once())->method('lock')->with('ergonode_attribute_synchronization_batch', 0)
            ->willReturn(true);
        $lock->expects(self::once())->method('unlock')->with('ergonode_attribute_synchronization_batch');

        $synchronization = $this->createMock(AttributeDefinitionSynchronizationInterface::class);
        $synchronization->expects(self::never())->method('synchronize');
        self::assertSame(
            ['has_more' => true, 'cursor' => 'next'],
            (new AttributeSnapshotRefresh(
                $synchronization,
                $importer,
                $lock
            ))->refreshSnapshot('browser-cursor', 25)
        );
    }

    public function testBusySnapshotCannotStartAnotherRead(): void
    {
        $importer = $this->createMock(AttributeDefinitionSnapshotInterface::class);
        $importer->expects(self::never())->method('page');
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(false);
        $this->expectException(LocalizedException::class);

        (new AttributeSnapshotRefresh(
            $this->createStub(AttributeDefinitionSynchronizationInterface::class),
            $importer,
            $lock
        ))->refreshSnapshot();
    }
}
