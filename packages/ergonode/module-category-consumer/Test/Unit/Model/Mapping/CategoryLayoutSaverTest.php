<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Mapping;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;
use Ergonode\Category\Model\Mapping\CategoryLayoutValidator;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingSaveHandler;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingDataPreparer;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingDataWriter;
use Ergonode\Category\Model\Mapping\CategoryMappingVisibility;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\ResourceModel\CategoryWriteTransaction;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CategoryLayoutSaverTest extends TestCase
{
    public function testRejectsDraggedTreeCycles(): void
    {
        $categoryCacheProvider = $this->createMock(CategoryCacheProvider::class);
        $categoryCacheProvider->method('getRowsByCode')->with(11)->willReturn([
            'a' => ['code' => 'a'],
            'b' => ['code' => 'b'],
        ]);

        $categoryTreeQuery = $this->createStub(CategoryTreeQuery::class);
        $categoryTreeQuery->method('getById')->willReturn([
            'category_tree_id' => 11,
            'root_category_id' => 2,
        ]);
        $categoryMappingVisibility = $this->createStub(CategoryMappingVisibility::class);
        $saver = new CategoryLayoutSaver(
            $categoryCacheProvider,
            $this->validator(
                $categoryTreeQuery,
                $categoryCacheProvider,
                $this->createStub(MagentoCategoryProvider::class)
            ),
            $this->createStub(LoggerInterface::class),
            $this->createStub(CategoryMappingWriter::class),
            $categoryTreeQuery,
            $categoryMappingVisibility,
            $this->createStub(CategoryMappingSaveHandler::class)
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Category tree contains a cycle near "a".');

        $saver->save(11, [
            ['code' => 'a', 'parent_code' => 'b', 'sort_order' => 0],
            ['code' => 'b', 'parent_code' => 'a', 'sort_order' => 1],
        ]);
    }

    public static function synchronizationWork(): array
    {
        return ['configured' => [true, 0], 'unused' => [false, 0]];
    }

    #[DataProvider('synchronizationWork')]
    public function testNewMappingPreservesExcludedTargetAndConfiguredData(?bool $hasWork, int $values): void
    {
        $categoryCacheProvider = $this->createMock(CategoryCacheProvider::class);
        $categoryCacheProvider->method('getRowsByCode')->with(11)->willReturn([
            'chairs' => [
                'code' => 'chairs',
                'manual_parent_code' => null,
                'manual_sort_order' => 0,
                'magento_category_id' => null,
            ],
        ]);
        $categoryTreeQuery = $this->createStub(CategoryTreeQuery::class);
        $categoryTreeQuery->method('getById')->willReturn([
            'category_tree_id' => 11,
            'tree_code' => 'main',
            'root_category_id' => 2,
        ]);
        $visibility = $this->createMock(CategoryMappingVisibility::class);
        $visibilityChanges = [['source' => 'magento', 'identifier' => '42', 'active' => false]];
        $visibility->expects(self::once())->method('save')->with(11, $visibilityChanges);
        $provider = $this->createMock(MagentoCategoryProvider::class);
        $provider->expects(self::once())->method('getIds')->with(2)->willReturn([42]);
        $writer = $this->createMock(CategoryMappingWriter::class);
        $writer->expects(self::once())->method('saveLayout')->with(11, 'chairs', null, 0, 42);
        $operations = $hasWork ? [['category_id' => 42, 'entity' => ['code' => 'chairs']]] : [];
        $preparer = $this->createMock(CategoryMappingDataPreparer::class);
        $preparer->expects(self::once())->method('prepare')->with(['chairs' => 42])->willReturn($operations);
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronize')->with([])
            ->willReturn(['attributes' => $values]);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $saver = new CategoryLayoutSaver(
            $categoryCacheProvider,
            $this->validator($categoryTreeQuery, $categoryCacheProvider, $provider),
            $this->createStub(LoggerInterface::class),
            $writer,
            $categoryTreeQuery,
            $visibility,
            new CategoryMappingSaveHandler(
                $preparer,
                new CategoryMappingDataWriter(
                    new CategoryWriteTransaction($resource),
                    $synchronizer,
                    $this->invalidator(),
                    $this->createStub(CategoryDataMappingProvider::class)
                )
            )
        );

        self::assertSame(
            ['updated' => 1, 'unchanged' => 0, 'attribute_values' => $values],
            $saver->save(11, [['code' => 'chairs', 'magento_category_id' => 42]], $visibilityChanges)
        );
    }
    private function validator(
        CategoryTreeQuery $query,
        CategoryCacheProvider $cache,
        MagentoCategoryProvider $magento
    ): CategoryLayoutValidator {
        return new CategoryLayoutValidator(
            $query,
            $this->createStub(CategoryTreeSourceState::class),
            $cache,
            $magento,
            $this->createStub(CategoryMappingQuery::class)
        );
    }

    private function invalidator(): CategoryCacheInvalidator
    {
        $invalidator = $this->createStub(CategoryCacheInvalidator::class);
        $invalidator->method('defer')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        return $invalidator;
    }
}
