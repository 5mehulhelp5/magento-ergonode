<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Mapping;

use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeMappingSaver;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\MappingSynchronization;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryAttributeMappingUpdater;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryOptionMappingSaver;
use Ergonode\CategoryAttributeConsumer\Model\Mapping\CategoryOptionMappingUpdater;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryMappedAttributeBackfiller;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryMappingUpdaterTest extends TestCase
{
    public function testAttributeMappingSaveAndBackfillShareOneLock(): void
    {
        $saver = $this->createMock(CategoryAttributeMappingSaver::class);
        $saver->expects(self::once())->method('save')->with([], [])->willReturn(['inserted' => 1]);
        $backfiller = $this->createMock(CategoryMappedAttributeBackfiller::class);
        $backfiller->expects(self::once())->method('execute')->willReturn([
            'categories' => 1,
            'values' => 2,
            'errors' => 0,
        ]);

        self::assertSame([
            'inserted' => 1,
            'value_sync' => ['categories' => 1, 'values' => 2, 'errors' => 0],
        ], (new CategoryAttributeMappingUpdater(
            $saver,
            new MappingSynchronization($backfiller, $this->lock())
        ))->save([], []));
    }

    public function testOptionMappingSaveAndBackfillShareOneLock(): void
    {
        $saver = $this->createMock(CategoryOptionMappingSaver::class);
        $saver->expects(self::once())->method('save')->with(7, [], [])->willReturn(['updated' => 1]);
        $backfiller = $this->createMock(CategoryMappedAttributeBackfiller::class);
        $backfiller->expects(self::once())->method('execute')->willReturn([
            'categories' => 1,
            'values' => 2,
            'errors' => 0,
        ]);

        self::assertSame([
            'updated' => 1,
            'value_sync' => ['categories' => 1, 'values' => 2, 'errors' => 0],
        ], (new CategoryOptionMappingUpdater(
            $saver,
            new MappingSynchronization($backfiller, $this->lock())
        ))->save(7, [], []));
    }

    private function lock(): CategorySynchronizationLock
    {
        $manager = $this->createMock(LockManagerInterface::class);
        $manager->expects(self::once())->method('lock')->willReturn(true);
        $manager->expects(self::once())->method('unlock');

        return new CategorySynchronizationLock($manager);
    }
}
