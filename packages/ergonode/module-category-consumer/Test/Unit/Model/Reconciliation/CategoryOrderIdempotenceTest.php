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
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class CategoryOrderIdempotenceTest extends TestCase
{
    public function testCorrectSiblingOrderWithPositionGapsDoesNotMoveCategories(): void
    {
        $writer = $this->createMock(CategoryPositionWriterInterface::class);
        $writer->expects(self::never())->method('move');
        $executor = $this->executor([90 => 21, 10 => 37], $writer);
        self::assertSame(0, $executor->executePass(7, 2, $this->sources(), $this->resolution())['moved']);
    }

    public function testReorderingMovesToBeginningAndASecondPassDoesNothing(): void
    {
        $writer = $this->createMock(CategoryPositionWriterInterface::class);
        $writer->expects(self::once())->method('move')->with(90, 2, 0);
        $executor = $this->executor([10 => 1, 90 => 2], $writer);
        $first = $executor->executePass(7, 2, $this->sources(), $this->resolution());
        self::assertSame([], $first['errors']);
        self::assertSame(1, $first['moved']);
        self::assertSame(0, $executor->executePass(7, 2, $this->sources(), $this->resolution())['moved']);
    }

    /** @param array<int, int> $positions */
    private function executor(array $positions, CategoryPositionWriterInterface $writer): CategoryReconciliationExecutor
    {
        $provider = new MagentoCategoryProvider($this->createStub(CollectionFactory::class));
        $categories = [];
        foreach ($positions as $id => $position) {
            $categories[$id] = [
                'id' => $id, 'parent_id' => 2, 'position' => $position,
                'path' => '1/2/' . $id, 'level' => 2, 'label' => (string)$id, 'url_key' => (string)$id,
            ];
        }
        (new ReflectionProperty($provider, 'categoriesCache'))->setValue($provider, [2 => $categories]);
        return new CategoryReconciliationExecutor(
            $provider,
            $this->createStub(CategoryMappingWriter::class),
            $this->createStub(CategoryCreationService::class),
            $writer,
            $this->createStub(MappedCategoryAttributeSynchronizerInterface::class),
            new ChangeReport(new Json()),
            $this->createStub(CategorySynchronizationProgress::class)
        );
    }

    /** @return list<array<string, mixed>> */
    private function sources(): array
    {
        return [
            ['code' => 'first', 'parent_code' => null, 'label' => 'First', 'sort_order' => 1],
            ['code' => 'second', 'parent_code' => null, 'label' => 'Second', 'sort_order' => 2],
        ];
    }

    /** @return array<string, mixed> */
    private function resolution(): array
    {
        return ['assignments' => [
            'first' => ['source' => 'database', 'magento_category_id' => 90, 'expected_parent_id' => 2],
            'second' => ['source' => 'database', 'magento_category_id' => 10, 'expected_parent_id' => 2],
        ]];
    }
}
