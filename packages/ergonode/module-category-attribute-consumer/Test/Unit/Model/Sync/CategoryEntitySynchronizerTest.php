<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryNameSynchronizerInterface;

use Ergonode\CategoryAttributeConsumer\Model\Snapshot\CategoryEntitySnapshotWriter;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeWriter;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryEntitySynchronizer;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryDataWorkProvider;
use Ergonode\Core\Model\Report\ChangeReport;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class CategoryEntitySynchronizerTest extends TestCase
{
    public function testSynchronizesMappedValuesAndNamesAndInvalidatesOnce(): void
    {
        $entity = [
            'code' => 'chairs',
            'labels' => ['en_US' => 'Chairs'],
            'attributes' => [['code' => 'color', 'type' => 'text', 'values' => ['en_US' => 'Red']]],
            'hash' => 'hash',
            'raw' => [],
        ];
        $snapshot = $this->createMock(CategoryEntitySnapshotWriter::class);
        $snapshot->expects(self::once())->method('save')->with($entity)->willReturn('updated');
        $writer = $this->createMock(CategoryAttributeWriter::class);
        $writer->expects(self::exactly(2))
            ->method('writeMappedValues')
            ->willReturn(2);
        $cache = $this->createMock(CategoryCacheInvalidator::class);
        $cache->expects(self::once())->method('invalidateCategories')->with([10, 20]);

        $names = $this->createMock(CategoryNameSynchronizerInterface::class);
        $names->expects(self::exactly(2))->method('synchronize')->willReturn(1);

        $result = (new CategoryEntitySynchronizer(
            $snapshot,
            $writer,
            $cache,
            new ChangeReport(new Json()),
            $names,
            $this->createStub(CategoryDataWorkProvider::class)
        ))->synchronize([
            ['category_id' => 10, 'entity' => $entity],
            ['category_id' => 20, 'entity' => $entity],
        ]);

        self::assertSame(1, $result['snapshots']);
        self::assertSame(6, $result['attributes']);
        self::assertSame([10, 20], $result['changed_category_ids']);
    }
}
