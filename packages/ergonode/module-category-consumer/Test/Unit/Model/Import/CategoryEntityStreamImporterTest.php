<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\CategoryStreamPageReader;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;

use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;

use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use PHPUnit\Framework\TestCase;

class CategoryEntityStreamImporterTest extends TestCase
{
    public function testFetchesRepeatedCodeOnceAndFansMappedAttributesOutToTwoActiveRoots(): void
    {
        $reader = $this->createMock(CategoryStreamPageReader::class);
        $reader->expects($this->once())->method('readAll')->willReturn([
            'codes' => ['chairs'],
            'cursor' => 'cursor-2',
        ]);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(null);
        $cursor->expects($this->once())->method('save')->with('category_stream', 'cursor-2');
        $mappingQuery = $this->createMock(CategoryDataMappingProvider::class);
        $mappingQuery->method('getValidMappingsByCodes')->with(['chairs'])->willReturn([
            'chairs' => [
                ['category_tree_id' => 7, 'magento_category_id' => 10],
                ['category_tree_id' => 8, 'magento_category_id' => 20],
            ],
        ]);
        $entity = [
            'code' => 'chairs',
            'labels' => ['en_US' => 'Chairs'],
            'attributes' => [['code' => 'color', 'type' => 'text', 'values' => ['en_US' => 'Red']]],
            'hash' => hash('sha256', 'chairs'),
            'raw' => [],
        ];
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects($this->once())->method('loadMany')->with(['chairs'])->willReturn(['chairs' => $entity]);
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronize')->with([
            ['category_id' => 10, 'entity' => $entity],
            ['category_id' => 20, 'entity' => $entity],
        ])->willReturn([
            'snapshots' => 1,
            'snapshot_statuses' => ['chairs' => 'updated'],
            'attributes' => 2,
            'changed_category_ids' => [10, 20],
        ]);

        $stats = (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $mappingQuery,
            $loader,
            $synchronizer,
            $this->languageMappingProvider(),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();

        self::assertSame(1, $stats['fetched']);
        self::assertSame(2, $stats['attributes']);
        self::assertSame(1, $stats['snapshots']);
    }

    public function testUnmappedCodeMovesCursorWithoutFetchingEntity(): void
    {
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => ['orphan'], 'cursor' => 'cursor-1']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(null);
        $cursor->expects($this->once())->method('save');
        $mappingQuery = $this->createStub(CategoryDataMappingProvider::class);
        $mappingQuery->method('getValidMappingsByCodes')->willReturn([]);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects($this->never())->method('loadMany');
        $synchronizer = $this->createStub(CategoryEntitySynchronizerInterface::class);
        $synchronizer->method('synchronize')->willReturn([
            'snapshots' => 0,
            'snapshot_statuses' => [],
            'attributes' => 0,
            'changed_category_ids' => [],
        ]);

        $stats = (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $mappingQuery,
            $loader,
            $synchronizer,
            $this->languageMappingProvider(),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();

        self::assertSame(0, $stats['fetched']);
        self::assertSame('cursor-1', $stats['cursor']);
    }

    public function testRateLimitDoesNotPersistSnapshotOrAdvanceCursor(): void
    {
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => ['chairs'], 'cursor' => 'cursor-1']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(null);
        $cursor->expects($this->never())->method('save');
        $mappingQuery = $this->createStub(CategoryDataMappingProvider::class);
        $mappingQuery->method('getValidMappingsByCodes')->willReturn([
            'chairs' => [['category_tree_id' => 7, 'magento_category_id' => 10]],
        ]);
        $loader = $this->createStub(CategoryEntityLoaderInterface::class);
        $loader->method('loadMany')->willThrowException(new GraphQlRequestException(
            'rate limited',
            GraphQlRequestException::FAILURE_RATE_LIMIT,
            429,
            20
        ));
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects($this->never())->method('synchronize');

        $this->expectException(GraphQlRequestException::class);
        (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $mappingQuery,
            $loader,
            $synchronizer,
            $this->languageMappingProvider(),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();
    }

    public function testRateLimitOnSecondEntityDoesNotPersistFirstSnapshotOrAdvanceCursor(): void
    {
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn([
            'codes' => ['chairs', 'tables'],
            'cursor' => 'cursor-2',
        ]);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(null);
        $cursor->expects($this->never())->method('save');
        $mappingQuery = $this->createStub(CategoryDataMappingProvider::class);
        $mappingQuery->method('getValidMappingsByCodes')->willReturn([
            'chairs' => [['category_tree_id' => 7, 'magento_category_id' => 10]],
            'tables' => [['category_tree_id' => 7, 'magento_category_id' => 11]],
        ]);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::once())->method('loadMany')->with(['chairs', 'tables'])
            ->willThrowException(new GraphQlRequestException(
                'rate limited',
                GraphQlRequestException::FAILURE_RATE_LIMIT,
                429,
                20
            ));
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects($this->never())->method('synchronize');

        $this->expectException(GraphQlRequestException::class);
        (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $mappingQuery,
            $loader,
            $synchronizer,
            $this->languageMappingProvider(),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();
    }

    public function testResetReadsFromTheBeginningAndKeepsOneSharedCursor(): void
    {
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->expects(self::once())->method('reset')->with('category_stream');
        $cursor->method('get')->with('category_stream')->willReturn(null);
        $cursor->expects(self::once())->method('save')->with('category_stream', 'latest');
        $reader = $this->createMock(CategoryStreamPageReader::class);
        $reader->expects(self::once())->method('readAll')->with(self::anything(), 'categoryStream', null)
            ->willReturn(['codes' => [], 'cursor' => 'latest']);
        $mappings = $this->createStub(CategoryDataMappingProvider::class);
        $mappings->method('getValidMappingsByCodes')->willReturn([]);
        $synchronizer = $this->createStub(CategoryEntitySynchronizerInterface::class);
        $synchronizer->method('synchronize')->willReturn(['snapshots' => 0, 'attributes' => 0]);

        $stats = (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $mappings,
            $this->createStub(CategoryEntityLoaderInterface::class),
            $synchronizer,
            $this->languageMappingProvider(),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute(true);
        self::assertSame('latest', $stats['cursor']);
    }

    private function languageMappingProvider(): LanguageStoreMappingProviderInterface
    {
        $provider = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $provider->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);

        return $provider;
    }
    public function testNoConfiguredDataWorkDoesNotQueryErgonodeOrAdvanceTheCursor(): void
    {
        $synchronizer = $this->createMockForIntersectionOfInterfaces([
            CategoryEntitySynchronizerInterface::class,
            CategoryDataWorkProviderInterface::class,
        ]);
        $synchronizer->method('hasWork')->willReturn(false);
        $synchronizer->expects(self::never())->method('synchronize');
        $reader = $this->createMock(CategoryStreamPageReader::class);
        $reader->expects(self::never())->method('readAll');
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::never())->method('loadMany');
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(['cursor' => 'existing']);
        $cursor->expects(self::never())->method('save');
        $stats = (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $this->createStub(CategoryDataMappingProvider::class),
            $loader,
            $synchronizer,
            $this->languageMappingProvider(),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();
        self::assertTrue($stats['skipped']);
        self::assertSame('existing', $stats['cursor']);
    }

    public function testLaterBatchFailureKeepsCompletedWritesAndDoesNotAdvanceCursor(): void
    {
        $codes = array_map(static fn (int $id): string => 'code-' . $id, range(1, 51));
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => $codes, 'cursor' => 'new']);
        $cursor = $this->createMock(CursorStorage::class);
        $cursor->method('get')->willReturn(['cursor' => 'old']);
        $cursor->expects(self::never())->method('save');
        $mapping = $this->createMock(CategoryDataMappingProvider::class);
        $mapping->expects(self::exactly(2))->method('clear');
        $mapping->method('getValidMappingsByCodes')->willReturnCallback(static function (array $batch): array {
            $result = [];
            foreach ($batch as $code) {
                $result[$code] = [['category_tree_id' => 7, 'magento_category_id' => (int)substr($code, 5)]];
            }
            return $result;
        });
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::exactly(2))->method('loadMany')->willReturnCallback(
            static function (array $batch): array {
                if (count($batch) === 1) {
                    throw new GraphQlRequestException('limited', GraphQlRequestException::FAILURE_RATE_LIMIT, 429, 20);
                }
                self::assertCount(50, $batch);
                $entities = [];
                foreach ($batch as $code) {
                    $entities[$code] = ['code' => $code, 'labels' => [], 'attributes' => [], 'hash' => '', 'raw' => []];
                }
                return $entities;
            }
        );
        $sync = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $sync->expects(self::once())->method('synchronize')->with(self::countOf(50))
            ->willReturn(['snapshots' => 50, 'attributes' => 50]);
        $importer = new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $mapping,
            $loader,
            $sync,
            $this->languageMappingProvider(),
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        );
        $this->expectException(GraphQlRequestException::class);
        $importer->execute();
    }
}
