<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Unit\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Snapshot\CategoryBackfillSnapshotReaderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeWriter;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryMappedAttributeBackfiller;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CategoryMappedAttributeBackfillerTest extends TestCase
{
    public function testChecksEachTreeBeforeDeduplicationAndRetainsEarlierCacheChange(): void
    {
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $reader = $this->createStub(CategoryBackfillSnapshotReaderInterface::class);
        $reader->method('getRows')->willReturn([
            $this->row(1, 'chairs', 10), $this->row(2, 'chairs', 10), $this->row(2, 'chairs', 10),
            $this->row(2, 'tables', 10), $this->row(1, 'chairs', 20),
        ]);
        $provider = $this->createMock(CategoryDataMappingProvider::class);
        $provider->expects(self::once())->method('clear');
        $provider->expects(self::once())->method('getValidMappingsByCodes')->willReturn([
            'chairs' => [['category_tree_id' => 2, 'magento_category_id' => 10]],
            'tables' => [['category_tree_id' => 2, 'magento_category_id' => 10]],
        ]);
        $writer = $this->createMock(CategoryAttributeWriter::class);
        $writer->expects(self::exactly(2))->method('writeMappedValues')->with(10, [])->willReturn(1, 0);
        $cache = $this->createMock(CategoryCacheInvalidator::class);
        $cache->expects(self::once())->method('invalidateCategories')->with([10]);
        $result = (new CategoryMappedAttributeBackfiller(
            $this->createStub(CategoryAttributeSourcePreparation::class),
            $reader,
            new Json(),
            $writer,
            new NullLogger(),
            $config,
            $provider,
            $cache
        ))->execute();
        self::assertSame(['categories' => 2, 'values' => 1, 'errors' => 0], $result);
    }

    public function testDisabledSynchronizationDoesNotReadOrWrite(): void
    {
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(false);
        $reader = $this->createMock(CategoryBackfillSnapshotReaderInterface::class);
        $reader->expects(self::never())->method('getRows');
        $writer = $this->createMock(CategoryAttributeWriter::class);
        $writer->expects(self::never())->method('writeMappedValues');
        $cache = $this->createMock(CategoryCacheInvalidator::class);
        $cache->expects(self::never())->method('invalidateCategories');
        self::assertSame(['categories' => 0, 'values' => 0, 'errors' => 0], (new CategoryMappedAttributeBackfiller(
            $this->createStub(CategoryAttributeSourcePreparation::class),
            $reader,
            new Json(),
            $writer,
            new NullLogger(),
            $config,
            $this->createStub(CategoryDataMappingProvider::class),
            $cache
        ))->execute());
    }

    /** @return array{category_tree_id: int, category_code: string, magento_category_id: int, attributes_json: string} */
    private function row(int $treeId, string $code, int $categoryId): array
    {
        return ['category_tree_id' => $treeId, 'category_code' => $code,
            'magento_category_id' => $categoryId, 'attributes_json' => '[]'];
    }
}
