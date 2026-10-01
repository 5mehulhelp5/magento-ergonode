<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeSyncCursorResetterInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Import\CategoryDataSyncCursorResetter;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationAction;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationPaused;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class CategorySynchronizationActionTest extends TestCase
{
    public function testAllUpdatesStructureBeforeDataAndPassesResetToBothProcesses(): void
    {
        $order = [];
        $tree = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $tree->expects(self::once())->method('execute')->with(true)->willReturnCallback(
            static function () use (&$order): array {
                $order[] = 'tree';
                return ['events' => 2, 'trees' => 1, 'conflicts' => 0, 'cursor' => 'tree'];
            }
        );
        $data = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $data->expects(self::once())->method('execute')->with(true)->willReturnCallback(
            static function () use (&$order): array {
                $order[] = 'data';
                return ['events' => 1, 'fetched' => 1, 'attributes' => 1, 'snapshots' => 0, 'cursor' => 'data'];
            }
        );
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $action = new CategorySynchronizationAction(
            $tree,
            $data,
            $this->createStub(CategoryTreeSyncCursorResetterInterface::class),
            $this->createStub(CategoryDataSyncCursorResetter::class),
            $config,
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CursorStorage::class)
        );

        self::assertSame(3, $action->execute('all', 'reset-cursor-and-sync')['events']);
        self::assertSame(['tree', 'data'], $order);
    }

    public function testResettingDataDoesNotResetOrRunTheTreeProcess(): void
    {
        $tree = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $tree->expects(self::never())->method('execute');
        $data = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $data->expects(self::never())->method('execute');
        $treeResetter = $this->createMock(CategoryTreeSyncCursorResetterInterface::class);
        $treeResetter->expects(self::never())->method('reset');
        $dataResetter = $this->createMock(CategoryDataSyncCursorResetter::class);
        $dataResetter->expects(self::once())->method('reset');

        $action = new CategorySynchronizationAction(
            $tree,
            $data,
            $treeResetter,
            $dataResetter,
            $this->createStub(CategoryConfigProvider::class),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CursorStorage::class)
        );
        self::assertSame([], $action->execute('data', 'reset-cursor')['results']);
    }
    public function testResumeReusesTheCompletedTreeStage(): void
    {
        $tree = $this->createMock(CategoryStructureSynchronizationProcessInterface::class);
        $tree->expects(self::never())->method('execute');
        $data = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
        $data->expects(self::once())->method('execute')->with(true)->willReturn(['events' => 2]);
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $progress = $this->createStub(CategorySynchronizationProgress::class);
        $progress->method('getStageResult')->willReturnMap([['tree', ['events' => 1]], ['data', null]]);
        $action = new CategorySynchronizationAction(
            $tree,
            $data,
            $this->createStub(CategoryTreeSyncCursorResetterInterface::class),
            $this->createStub(CategoryDataSyncCursorResetter::class),
            $config,
            $progress,
            $this->createStub(CursorStorage::class)
        );
        self::assertSame(3, $action->execute('all', 'reset-cursor-and-sync')['events']);
    }

    public function testManagedRunCommitsOnlyAfterBothStagesFinish(): void
    {
        $order = [];
        $tree = $this->createStub(CategoryStructureSynchronizationProcessInterface::class);
        $tree->method('execute')->willReturnCallback(static function () use (&$order): array {
            $order[] = 'tree';
            return ['events' => 1, 'conflicts' => 0, 'cursor' => 'tree-next'];
        });
        $data = $this->createStub(CategoryDataSynchronizationProcessInterface::class);
        $data->method('execute')->willReturnCallback(static function () use (&$order): array {
            $order[] = 'data';
            return ['events' => 1, 'cursor' => 'data-next'];
        });
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (string $process, string $value) use (&$order): void {
                $order[] = [$process, $value];
            }
        );
        $this->managedAction($tree, $data, $cursor)->execute('all', 'sync');
        self::assertSame(['tree', 'data', ['category_tree_stream', 'tree-next'],
            ['category_stream', 'data-next']], $order);
    }

    public function testManagedRunDoesNotCommitEitherCursorWhenDataIsPausedOrFails(): void
    {
        foreach ([new RuntimeException('data failed'), new CategorySynchronizationPaused()] as $failure) {
            $tree = $this->createStub(CategoryStructureSynchronizationProcessInterface::class);
            $tree->method('execute')->willReturn(['events' => 1, 'conflicts' => 0, 'cursor' => 'tree-next']);
            $data = $this->createStub(CategoryDataSynchronizationProcessInterface::class);
            $data->method('execute')->willThrowException($failure);
            $cursor = $this->createMock(CursorStorage::class);
            $cursor->expects(self::never())->method('save');
            try {
                $this->managedAction($tree, $data, $cursor)->execute('all', 'sync');
                self::fail('The interrupted stage must propagate its failure.');
            } catch (Throwable $exception) {
                self::assertSame($failure, $exception);
            }
        }
    }

    public function testManagedRunKeepsCursorsWhenReconciliationHasConflicts(): void
    {
        $tree = $this->createStub(CategoryStructureSynchronizationProcessInterface::class);
        $tree->method('execute')->willReturn(['events' => 1, 'conflicts' => 1, 'cursor' => 'old']);
        $data = $this->createStub(CategoryDataSynchronizationProcessInterface::class);
        $data->method('execute')->willReturn(['events' => 1, 'cursor' => 'data-next']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->expects(self::never())->method('save');
        self::assertSame(1, $this->managedAction($tree, $data, $cursor)->execute('all', 'sync')['conflicts']);
    }

    public function testExcludedBranchAllowsBothManagedCursorsToAdvance(): void
    {
        $resolver = new CategoryIdentityResolver(
            new CategoryNameNormalizer(),
            new CategoryDeletionCandidateResolver()
        );
        foreach ([false, true] as $sourceExcluded) {
            foreach ([true, false] as $childActive) {
                $resolution = $resolver->resolve(
                    2,
                    [
                        ['code' => 'p', 'parent_code' => null, 'label' => 'Parent', 'active' => !$sourceExcluded],
                        ['code' => 'c', 'parent_code' => 'p', 'label' => 'Child', 'active' => $childActive],
                        ['code' => 'g', 'parent_code' => 'c', 'label' => 'Grandchild'],
                    ],
                    [10 => ['id' => 10, 'parent_id' => 2, 'active' => $sourceExcluded]],
                    $sourceExcluded ? ['c' => 10] : ['p' => 10]
                );
                self::assertSame([], $resolution['conflicts']);
                if ($sourceExcluded) {
                    self::assertSame(10, $resolution['assignments']['c']['magento_category_id']);
                }
            }
        }
        $tree = $this->createStub(CategoryStructureSynchronizationProcessInterface::class);
        $tree->method('execute')->willReturn([
            'events' => 1, 'conflicts' => count($resolution['conflicts']), 'cursor' => 'tree-next',
        ]);
        $data = $this->createStub(CategoryDataSynchronizationProcessInterface::class);
        $data->method('execute')->willReturn(['events' => 1, 'cursor' => 'data-next']);
        $saved = [];
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->expects(self::exactly(2))->method('save')->willReturnCallback(
            static function (string $process, string $value) use (&$saved): void {
                $saved[$process] = $value;
            }
        );
        self::assertSame(0, $this->managedAction($tree, $data, $cursor)->execute('all', 'sync')['conflicts']);
        self::assertSame(['category_tree_stream' => 'tree-next', 'category_stream' => 'data-next'], $saved);
    }

    private function managedAction(
        CategoryStructureSynchronizationProcessInterface $tree,
        CategoryDataSynchronizationProcessInterface $data,
        CursorStorage $cursor
    ): CategorySynchronizationAction {
        $progress = $this->createStub(CategorySynchronizationProgress::class);
        $progress->method('isManaged')->willReturn(true);
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        return new CategorySynchronizationAction(
            $tree,
            $data,
            $this->createStub(CategoryTreeSyncCursorResetterInterface::class),
            $this->createStub(CategoryDataSyncCursorResetter::class),
            $config,
            $progress,
            $cursor
        );
    }
}
