<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Pipeline;

use Ergonode\Product\Model\Cache\ProductCacheFinalizer;
use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\Magento\ProductIndexInvalidator;
use Ergonode\ProductConsumer\Model\Pipeline\{BatchContext, BatchEntry, FinishBatch};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class FinishBatchTest extends TestCase
{
    public function testOnlyChangedCompletedProductsArePublishedIncludingMediaOnlyChanges(): void
    {
        $entries = [];
        foreach ([1, 2, 3] as $id) {
            $entry = new BatchEntry(new ProductImportWorkItem($id, 'sku' . $id, 'sync', null, 'e', 'l', 1));
            $entry->productId = $id; $entries[] = $entry;
        }
        $entries[1]->error = new RuntimeException('media failed');
        $context = new BatchContext($entries, new NullLogger());
        $context->before = [1 => 'old image', 2 => 'old data', 3 => 'unchanged'];
        $cache = $this->createMock(ProductCacheFinalizer::class);
        $cache->expects(self::once())->method('changes')->with([1, 2, 3], $context->before)->willReturn([1 => 'new image', 2 => 'partial data']);
        $cache->expects(self::once())->method('complete')->with([1 => 'new image']);
        $indexes = $this->createMock(ProductIndexInvalidator::class);
        $indexes->expects(self::once())->method('invalidate');
        (new FinishBatch($cache, $indexes))->process($context);
        self::assertTrue($entries[0]->changed);
        self::assertTrue($entries[1]->changed);
        self::assertFalse($entries[2]->changed);
    }
}
