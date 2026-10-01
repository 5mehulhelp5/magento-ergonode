<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Test\Unit\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\OptionCacheReconciler;
use Ergonode\AttributeConsumer\Model\Provider\OptionSnapshotCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class OptionCacheReconcilerTest extends TestCase
{
    public function testZeroSurvivesTrimmingDeduplicationAndEmptyCodeFiltering(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete')->with('ergonode_attribute_option', [
            'attribute_code = ?' => 'size', 'option_code NOT IN (?)' => ['0', '1'],
        ])->willReturn(2);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('ergonode_attribute_option');
        $cache = new OptionSnapshotCache();
        $cache->definitions = ['size' => [['code' => 'old', 'labels' => []]]];

        self::assertSame(2, (new OptionCacheReconciler($resource, $cache))->reconcile(' size ', [' 0 ', '', '1', '0']));
        self::assertSame([], $cache->definitions);
    }
}
