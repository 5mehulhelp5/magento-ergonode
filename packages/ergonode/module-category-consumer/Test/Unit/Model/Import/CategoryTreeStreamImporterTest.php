<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Import;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;

use Ergonode\Category\Model\GraphQl\CategoryQueries;

use Ergonode\Category\Model\Import\CategoryStreamPageReader;
use Ergonode\CategoryConsumer\Api\CategoryReconciliationServiceInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationResultInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\Category\Model\Reconciliation\CategoryNameNormalizer;
use Ergonode\Category\Model\Reconciliation\CategoryDeletionCandidateResolver;

class CategoryTreeStreamImporterTest extends TestCase
{
    public function testPreservedMissingParentWithUnmanagedChildDoesNotHoldCursor(): void
    {
        $resolver = new CategoryIdentityResolver(new CategoryNameNormalizer(), new CategoryDeletionCandidateResolver());
        $resolution = $resolver->resolve(2, [], [
                10 => ['id' => 10, 'parent_id' => 2, 'label' => 'Missing', 'level' => 2],
                11 => ['id' => 11, 'parent_id' => 10, 'label' => 'Magento only', 'level' => 3],
            ], ['missing' => 10]);
        self::assertFalse($resolution['deletion_allowed']);
        $this->assertCursorAdvances($resolution);
    }

    public function testExcludedTargetWithUnmappedDescendantsDoesNotHoldCursor(): void
    {
        $resolver = new CategoryIdentityResolver(new CategoryNameNormalizer(), new CategoryDeletionCandidateResolver());
        $resolution = $resolver->resolve(2, [
            ['code' => 'p', 'parent_code' => null, 'label' => 'Parent'],
            ['code' => 'c', 'parent_code' => 'p', 'label' => 'Child'],
            ['code' => 'g', 'parent_code' => 'c', 'label' => 'Grandchild'],
        ], [10 => ['id' => 10, 'parent_id' => 2, 'active' => false]], ['p' => 10]);
        $this->assertCursorAdvances($resolution);
    }

    /** @param array<string, mixed> $resolution */
    private function assertCursorAdvances(array $resolution): void
    {
        $result = $this->createStub(CategoryReconciliationResultInterface::class);
        $result->method('getConflicts')->willReturn($resolution['conflicts']);
        $service = $this->createStub(CategoryReconciliationServiceInterface::class);
        $service->method('execute')->willReturn($result);
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => ['tree'], 'cursor' => 'next']);
        $trees = $this->createStub(CategoryTreeQuery::class);
        $trees->method('getSynchronizableByTreeCodes')->willReturn([['category_tree_id' => 7, 'tree_code' => 'tree']]);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(['cursor' => 'previous']);
        $cursor->expects(self::once())->method('save')->with('category_tree_stream', 'next');

        $stats = (new CategoryTreeStreamImporter(
            $reader,
            $cursor,
            $trees,
            $service,
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();

        self::assertSame(0, $stats['conflicts']);
        self::assertSame('next', $stats['cursor']);
    }

    public function testMissingTreeWithoutDeletionEventIsReportedWhileHealthyTreeRuns(): void
    {
        $availability = $this->createStub(CategorySourceAvailability::class);
        $availability->method('checkActiveTrees')->willReturn([7 => 'Source default was not found.']);
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => ['healthy'], 'cursor' => 'next']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(['cursor' => 'previous']);
        $cursor->expects(self::never())->method('save');
        $trees = $this->createStub(CategoryTreeQuery::class);
        $trees->method('getById')->willReturn(['tree_code' => 'default']);
        $trees->method('getSynchronizableByTreeCodes')->willReturn([
            ['category_tree_id' => 8, 'tree_code' => 'healthy'],
        ]);
        $service = $this->createMock(CategoryReconciliationServiceInterface::class);
        $service->expects(self::once())->method('execute')->with(self::callback(
            static fn (CategoryReconciliationRequestInterface $request): bool => $request->getCategoryTreeId() === 8
        ))->willReturn($this->createStub(CategoryReconciliationResultInterface::class));
        $result = (new CategoryTreeStreamImporter(
            $reader,
            $cursor,
            $trees,
            $service,
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class),
            $availability
        ))->execute();

        self::assertSame(1, $result['conflicts']);
        self::assertSame('previous', $result['cursor']);
        self::assertSame([7, 8], array_column($result['tree_results'], 'category_tree_id'));
    }

    public function testAppliesAllRootsForChangedTreeAndAdvancesPastUnmappedEvents(): void
    {
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn([
            'codes' => ['shared', 'unmapped'],
            'cursor' => 'cursor-2',
        ]);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn([
            'cursor' => 'cursor-1',
            'process_code' => '',
        ]);
        $cursor->expects($this->once())->method('save')->with('category_tree_stream', 'cursor-2');
        $treeQuery = $this->createMock(CategoryTreeQuery::class);
        $treeQuery->expects($this->once())
            ->method('getSynchronizableByTreeCodes')
            ->with(['shared', 'unmapped'])
            ->willReturn([
                ['category_tree_id' => 7, 'tree_code' => 'shared'],
                ['category_tree_id' => 8, 'tree_code' => 'shared'],
            ]);
        $result = $this->createStub(CategoryReconciliationResultInterface::class);
        $result->method('getConflicts')->willReturn([]);
        $seen = [];
        $service = $this->createMock(CategoryReconciliationServiceInterface::class);
        $service->expects($this->exactly(2))->method('execute')->willReturnCallback(
            static function (CategoryReconciliationRequestInterface $request) use (&$seen, $result) {
                $seen[] = [$request->getCategoryTreeId(), $request->getMode()];

                return $result;
            }
        );

        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);
        $stats = (new CategoryTreeStreamImporter(
            $reader,
            $cursor,
            $treeQuery,
            $service,
            $languageMappingProvider,
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();

        self::assertSame([
            [7, CategoryReconciliationRequestInterface::MODE_APPLY],
            [8, CategoryReconciliationRequestInterface::MODE_APPLY],
        ], $seen);
        self::assertSame(2, $stats['events']);
        self::assertSame('cursor-2', $stats['cursor']);
    }

    public function testResetConsumesStreamAndAppliesEveryActiveConfiguredTree(): void
    {
        $reader = $this->createMock(CategoryStreamPageReader::class);
        $reader->expects(self::once())
            ->method('readAll')
            ->with(CategoryQueries::CATEGORY_TREE_STREAM, 'categoryTreeStream', null)
            ->willReturn([
                'codes' => ['changed', 'unmapped'],
                'cursor' => 'cursor-latest',
            ]);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->expects(self::once())->method('reset')->with('category_tree_stream');
        $cursor->expects(self::never())->method('get');
        $cursor->expects(self::once())->method('save')->with('category_tree_stream', 'cursor-latest');
        $treeQuery = $this->createMock(CategoryTreeQuery::class);
        $treeQuery->expects(self::never())->method('getSynchronizableByTreeCodes');
        $treeQuery->expects(self::once())->method('getList')->with(true)->willReturn([
            ['category_tree_id' => 7, 'tree_code' => 'shared'],
            ['category_tree_id' => 8, 'tree_code' => 'shared'],
        ]);
        $result = $this->createStub(CategoryReconciliationResultInterface::class);
        $result->method('getConflicts')->willReturn([]);
        $service = $this->createMock(CategoryReconciliationServiceInterface::class);
        $service->expects(self::exactly(2))->method('execute')->willReturn($result);
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);

        $stats = (new CategoryTreeStreamImporter(
            $reader,
            $cursor,
            $treeQuery,
            $service,
            $languageMappingProvider,
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute(true);

        self::assertSame(2, $stats['events']);
        self::assertSame(2, $stats['trees']);
        self::assertSame('cursor-latest', $stats['cursor']);
    }

    public function testResetLeavesCursorClearedWhenReconciliationFails(): void
    {
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn([
            'codes' => [],
            'cursor' => 'cursor-latest',
        ]);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->expects(self::once())->method('reset')->with('category_tree_stream');
        $cursor->expects(self::never())->method('get');
        $cursor->expects(self::never())->method('save');
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getList')->willReturn([['category_tree_id' => 7, 'tree_code' => 'shared']]);
        $service = $this->createStub(CategoryReconciliationServiceInterface::class);
        $service->method('execute')->willThrowException(new RuntimeException('reconciliation failed'));
        $languageMappingProvider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languageMappingProvider->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);

        $this->expectException(RuntimeException::class);
        (new CategoryTreeStreamImporter(
            $reader,
            $cursor,
            $treeQuery,
            $service,
            $languageMappingProvider,
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute(true);
    }

    public function testConflictsKeepOldCursorSoAnOrdinarySyncRetriesTheTree(): void
    {
        $reader = $this->createMock(CategoryStreamPageReader::class);
        $reader->expects(self::exactly(2))->method('readAll')
            ->with(CategoryQueries::CATEGORY_TREE_STREAM, 'categoryTreeStream', 'old')
            ->willReturn(['codes' => ['tree'], 'cursor' => 'next']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(['cursor' => 'old']);
        $cursor->expects(self::once())->method('save')->with('category_tree_stream', 'next');
        $trees = $this->createStub(CategoryTreeQuery::class);
        $trees->method('getSynchronizableByTreeCodes')->willReturn([
            ['category_tree_id' => 7, 'tree_code' => 'tree']
        ]);
        $failed = $this->createStub(CategoryReconciliationResultInterface::class);
        $failed->method('getConflicts')->willReturn(['Could not create a category.']);
        $success = $this->createStub(CategoryReconciliationResultInterface::class);
        $success->method('getConflicts')->willReturn([]);
        $service = $this->createMock(CategoryReconciliationServiceInterface::class);
        $service->expects(self::exactly(2))->method('execute')->willReturn($failed, $success);
        $importer = new CategoryTreeStreamImporter(
            $reader,
            $cursor,
            $trees,
            $service,
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        );
        self::assertSame('old', $importer->execute()['cursor']);
        self::assertSame('next', $importer->execute()['cursor']);
    }

    public function testManagedImporterLeavesCursorCommitToTheCompleteRun(): void
    {
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => ['tree'], 'cursor' => 'next']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(['cursor' => 'old']);
        $cursor->expects(self::never())->method('save');
        $trees = $this->createStub(CategoryTreeQuery::class);
        $trees->method('getSynchronizableByTreeCodes')->willReturn([
            ['category_tree_id' => 7, 'tree_code' => 'tree']
        ]);
        $service = $this->createStub(CategoryReconciliationServiceInterface::class);
        $service->method('execute')->willReturn($this->createStub(CategoryReconciliationResultInterface::class));
        $progress = $this->createStub(CategorySynchronizationProgress::class);
        $progress->method('isManaged')->willReturn(true);
        $importer = new CategoryTreeStreamImporter(
            $reader,
            $cursor,
            $trees,
            $service,
            $this->createStub(LanguageStoreMappingProviderInterface::class),
            new ChangeReport(new Json()),
            $progress,
            $this->createStub(CategorySourceAvailability::class)
        );
        self::assertSame('next', $importer->execute()['cursor']);
    }
}
