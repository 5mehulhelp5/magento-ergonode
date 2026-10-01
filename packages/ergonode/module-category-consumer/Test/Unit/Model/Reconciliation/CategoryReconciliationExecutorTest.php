<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Reconciliation;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;

use Ergonode\CategoryConsumer\Api\CategoryPositionWriterInterface;
use Ergonode\CategoryConsumer\Api\MappedCategoryAttributeSynchronizerInterface;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationExecutor;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreationService;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryReconciliationExecutorTest extends TestCase
{
    public function testCreatesAndMapsUnmatchedCategoryBeforeChildren(): void
    {
        $category = [
            'id' => 10,
            'parent_id' => 2,
            'label' => 'Chairs',
            'path' => '1/2/10',
            'level' => 2,
            'position' => 1,
            'url_key' => 'chairs',
        ];
        $provider = $this->createMock(MagentoCategoryProvider::class);
        $provider->expects($this->once())->method('addOrUpdate')->with(2, $category);
        $provider->expects($this->once())->method('getCategory')->with(10, 2)->willReturn($category);
        $creator = $this->createMock(CategoryCreationService::class);
        $creator->expects($this->once())->method('create')->with('chairs', 'Chairs', 2)->willReturn($category);
        $mapping = $this->createMock(CategoryMappingWriter::class);
        $mapping->expects($this->exactly(2))->method('updateMagentoLink');
        $management = $this->createMock(CategoryPositionWriterInterface::class);
        $management->expects($this->never())->method('move');
        $attributeSynchronizer = $this->createMock(MappedCategoryAttributeSynchronizerInterface::class);
        $attributeSynchronizer->expects(self::never())->method('synchronize');
        $progress = $this->createMock(CategorySynchronizationProgress::class);
        $progress->expects(self::once())->method('completedOperation')->with('created');
        $executor = new CategoryReconciliationExecutor(
            $provider,
            $mapping,
            $creator,
            $management,
            $attributeSynchronizer,
            new ChangeReport(new Json()),
            $progress
        );

        $result = $executor->executePass(7, 2, [[
            'code' => 'chairs',
            'parent_code' => null,
            'label' => 'Chairs',
            'labels' => ['en_US' => 'Chairs'],
            'sort_order' => 0,
        ]], [
            'assignments' => [
                'chairs' => ['magento_category_id' => null, 'source' => 'unmatched', 'expected_parent_id' => 2],
            ],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(['chairs' => 10], $result['mappings']);
        self::assertSame([], $result['errors']);
    }

    public function testReordersSiblingsWithOneMove(): void
    {
        $provider = $this->createMock(MagentoCategoryProvider::class);
        $categories = [
            10 => [
                'id' => 10, 'parent_id' => 2, 'label' => 'First', 'path' => '1/2/10',
                'level' => 2, 'position' => 1, 'url_key' => 'first',
            ],
            11 => [
                'id' => 11, 'parent_id' => 2, 'label' => 'Second', 'path' => '1/2/11',
                'level' => 2, 'position' => 2, 'url_key' => 'second',
            ],
        ];
        $provider->method('getCategory')->willReturnCallback(
            static function (int $categoryId) use (&$categories): array {
                return $categories[$categoryId];
            }
        );
        $provider->method('getPreviousSiblingId')->willReturnCallback(
            static function (int $id) use (&$categories): int {
                return $categories[$id]['position'] === 1 ? 0 : ($id === 10 ? 11 : 10);
            }
        );
        $provider->expects($this->once())->method('markMoved')->with(2, 11, 2, 1)->willReturnCallback(
            static function () use (&$categories): void {
                $categories[11]['position'] = 1;
                $categories[10]['position'] = 2;
            }
        );
        $management = $this->createMock(CategoryPositionWriterInterface::class);
        $management->expects($this->once())->method('move')->with(11, 2, 0);
        $mapping = $this->createStub(CategoryMappingWriter::class);
        $creator = $this->createMock(CategoryCreationService::class);
        $creator->expects($this->never())->method('create');
        $executor = new CategoryReconciliationExecutor(
            $provider,
            $mapping,
            $creator,
            $management,
            $this->createStub(MappedCategoryAttributeSynchronizerInterface::class),
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class)
        );
        $sources = [
            ['code' => 'second', 'parent_code' => null, 'label' => 'Second', 'labels' => [], 'sort_order' => 0],
            ['code' => 'first', 'parent_code' => null, 'label' => 'First', 'labels' => [], 'sort_order' => 1],
        ];
        $resolution = ['assignments' => [
            'second' => ['magento_category_id' => 11, 'source' => 'database', 'expected_parent_id' => 2],
            'first' => ['magento_category_id' => 10, 'source' => 'database', 'expected_parent_id' => 2],
        ]];

        $first = $executor->executePass(7, 2, $sources, $resolution);

        self::assertSame(1, $first['moved']);
    }

    public function testSynchronizesAttributesWhenExistingCategoryGetsItsFirstMapping(): void
    {
        $provider = $this->createStub(MagentoCategoryProvider::class);
        $provider->method('getCategory')->willReturn([
            'id' => 10,
            'parent_id' => 2,
            'label' => 'Chairs',
            'path' => '1/2/10',
            'level' => 2,
            'position' => 1,
            'url_key' => 'chairs',
        ]);
        $attributeSynchronizer = $this->createMock(MappedCategoryAttributeSynchronizerInterface::class);
        $attributeSynchronizer->expects(self::once())->method('synchronize')->with('chairs', 10);
        $executor = new CategoryReconciliationExecutor(
            $provider,
            $this->createStub(CategoryMappingWriter::class),
            $this->createStub(CategoryCreationService::class),
            $this->createStub(CategoryPositionWriterInterface::class),
            $attributeSynchronizer,
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class)
        );

        $result = $executor->executePass(7, 2, [[
            'code' => 'chairs',
            'parent_code' => null,
            'label' => 'Chairs',
            'labels' => ['en_US' => 'Chairs'],
            'sort_order' => 0,
        ]], [
            'assignments' => [
                'chairs' => ['magento_category_id' => 10, 'source' => 'draft', 'expected_parent_id' => 2],
            ],
        ]);

        self::assertSame(['chairs' => 10], $result['mappings']);
        self::assertSame([], $result['errors']);
    }
}
