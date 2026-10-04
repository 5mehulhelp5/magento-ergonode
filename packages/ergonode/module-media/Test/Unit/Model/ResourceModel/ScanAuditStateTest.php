<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\ResourceModel;

use Ergonode\Media\Model\ResourceModel\ScanState;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class ScanAuditStateTest extends TestCase
{
    public function testVerificationPersistsItsOwnDateWithoutChangingTheFullScanMarker(): void
    {
        $writes = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::exactly(5))->method('insertOnDuplicate')->willReturnCallback(
            static function (string $table, array $rows, array $columns) use (&$writes): int {
                self::assertSame('ergonode_media_scan', $table);
                self::assertSame(array_keys(array_diff_key($rows[0], ['state_id' => 1])), $columns);
                $writes[] = $rows[0];
                return 1;
            }
        );
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $state = new ScanState($resource);
        $state->request(20, true);
        $state->begin(20, true);
        $state->complete(true, 'One file missing; see logs.');
        $state->request(30);
        $state->complete();
        self::assertSame('audit_pending', $writes[0]['status']);
        self::assertSame('auditing', $writes[1]['status']);
        self::assertSame('audited', $writes[2]['status']);
        self::assertSame('One file missing; see logs.', $writes[2]['error']);
        self::assertGreaterThan(0, $writes[2]['verification_completed_at']);
        foreach (array_slice($writes, 0, 4) as $write) {
            self::assertArrayNotHasKey('last_completed_at', $write);
        }
        foreach ([$writes[0], $writes[1], $writes[3], $writes[4]] as $write) {
            self::assertArrayNotHasKey('verification_completed_at', $write);
        }
        self::assertGreaterThan(0, $writes[4]['last_completed_at']);
    }
}
