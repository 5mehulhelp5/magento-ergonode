<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Console\Command;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Console\Command\SyncCategoryTreesCommand;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SyncCategoryTreesCommandTest extends TestCase
{
    public function testCommandUsesCategoryTreesSyncName(): void
    {
        $command = new SyncCategoryTreesCommand(
            $this->createStub(CategoryStructureSynchronizationProcessInterface::class),
            new CategoryReconciliationErrorFormatter()
        );

        self::assertSame('ergonode:category-trees:sync', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('reset-cursor'));
    }

    public function testRunsIncrementalSynchronizationByDefault(): void
    {
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->with(false)->willReturn($this->stats());

        $tester = new CommandTester(new SyncCategoryTreesCommand(
            $process,
            new CategoryReconciliationErrorFormatter()
        ));

        self::assertSame(0, $tester->execute([]));
    }

    public function testResetCursorRunsFullSynchronization(): void
    {
        $process = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $process->expects(self::once())->method('execute')->with(true)->willReturn($this->stats());

        $tester = new CommandTester(new SyncCategoryTreesCommand(
            $process,
            new CategoryReconciliationErrorFormatter()
        ));

        self::assertSame(0, $tester->execute(['--reset-cursor' => true]));
    }

    /** @return array{events: int, trees: int, conflicts: int, cursor: string} */
    private function stats(): array
    {
        return ['events' => 0, 'trees' => 0, 'conflicts' => 0, 'cursor' => 'cursor'];
    }
}
