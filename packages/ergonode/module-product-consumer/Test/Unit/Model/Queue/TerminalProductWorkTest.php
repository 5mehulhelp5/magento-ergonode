<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Queue;

use Ergonode\ProductConsumer\Model\Data\ProductImportWorkItem;
use Ergonode\ProductConsumer\Model\ResourceModel\ProductImportWorkRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TerminalProductWorkTest extends TestCase
{
    private function repo(AdapterInterface $db, LoggerInterface $logger): ProductImportWorkRepository
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($db);
        $resource->method('getTableName')->willReturnArgument(0);
        $clock = $this->createStub(DateTime::class); $clock->method('gmtDate')->willReturn('2026-10-04 12:00:00');
        return new ProductImportWorkRepository($resource, new Json(), $clock, $logger);
    }

    public function testEvenDependencyFailureAtFirstAttemptIsTerminalWithNoFutureDelay(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->expects(self::once())->method('update')->with('ergonode_product_import_item', self::callback(
            static fn(array $values): bool => $values['status'] === 'failed' && $values['lease_token'] === null
                && $values['available_at'] === '2026-10-04 12:00:00'
        ), self::callback(static fn(array $where): bool => $where['event_token = ?'] === 'event' && $where['lease_token = ?'] === 'lease'));
        $this->repo($db, $this->createStub(LoggerInterface::class))->release(
            new ProductImportWorkItem(1, 'SKU', 'sync', null, 'event', 'lease', 1), 'dependency missing', 8, 900, true
        );
    }

    public function testExpiredProductIsLoggedAndNotClaimedAgain(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $conditions = [];
        $select = $this->createStub(Select::class);
        foreach (['from', 'order', 'limit', 'forUpdate'] as $method) { $select->method($method)->willReturnSelf(); }
        $select->method('where')->willReturnCallback(function (string $where) use (&$conditions, $select): Select {
            $conditions[] = $where; return $select;
        });
        $db->method('select')->willReturn($select); $db->method('quoteInto')->willReturnArgument(0);
        $db->expects(self::exactly(2))->method('fetchAll')->willReturnOnConsecutiveCalls([
            ['item_id' => 1, 'ergonode_sku' => 'SKU', 'event_token' => 'event'],
        ], []);
        $db->expects(self::once())->method('update')->with(self::anything(), self::callback(
            static fn(array $values): bool => $values['status'] === 'failed'
        ), self::anything())->willReturn(1);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::anything(), ['ergonode_sku' => 'SKU', 'item_id' => 1]);
        self::assertSame([], $this->repo($db, $logger)->claim(25, 900));
        self::assertStringContainsString('attempt_count = 0', end($conditions));
        self::assertStringNotContainsString(' OR ', end($conditions));
    }
}
