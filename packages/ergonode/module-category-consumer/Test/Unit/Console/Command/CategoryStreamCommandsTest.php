<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Console\Command;

use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Console\Command\SyncCategoryDataCommand;
use Ergonode\CategoryConsumer\Console\Command\SyncCategoryTreesCommand;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CategoryStreamCommandsTest extends TestCase
{
    public function testStructureCommandUsesSharedProcessAndFailsOnConflicts(): void
    {
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willReturn([
            'events' => 2,
            'trees' => 1,
            'conflicts' => 1,
            'cursor' => 'tree-cursor',
        ]);
        $tester = new CommandTester(new SyncCategoryTreesCommand(
            $process,
            new CategoryReconciliationErrorFormatter()
        ));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('conflicts=1', $tester->getDisplay());
    }

    public function testAttributeCommandUsesSharedProcessIndependently(): void
    {
        $process = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->willReturn([
            'events' => 2,
            'fetched' => 1,
            'snapshots' => 1,
            'attributes' => 3,
            'cursor' => 'attribute-cursor',
        ]);
        $tester = new CommandTester(new SyncCategoryDataCommand(
            $process,
            new CategoryReconciliationErrorFormatter()
        ));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('attributes=3', $tester->getDisplay());
    }
}
