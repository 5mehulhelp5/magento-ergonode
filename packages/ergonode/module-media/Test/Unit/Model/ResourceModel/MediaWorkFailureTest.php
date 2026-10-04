<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\ResourceModel;

use Ergonode\Media\Model\Data\WorkItem;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MediaWorkFailureTest extends TestCase
{
    public function testFailedAttemptIsTerminalAndCannotOverwriteANewerPass(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('update')->with('ergonode_media_product_work', [
            'status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null, 'last_error' => 'invalid media',
        ], ['product_id = ?' => 23, 'lease_token = ?' => 'old-token']);
        $connection->expects(self::never())->method('insertOnDuplicate');
        $this->repository($connection)->fail(new WorkItem(23, 'old-token', 1), 'invalid media');
    }

    #[DataProvider('unfinished')]
    public function testInterruptedAndLegacyRetryWorkIsLoggedAndNeverClaimed(array $row): void
    {
        $conditions = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->select($conditions));
        $connection->method('quoteInto')->willReturnCallback(static fn ($q, $v): string =>
            str_replace('?', is_int($v) ? (string)$v : "'" . $v . "'", $q));
        $connection->expects(self::exactly(2))->method('fetchAll')->willReturnOnConsecutiveCalls([$row], []);
        $connection->expects(self::once())->method('update')->willReturnCallback(
            static function ($table, $values, $where) use ($row): int {
                self::assertSame('failed', $values['status']);
                self::assertNull($values['lease_token']);
                self::assertSame($row['status'], $where['status = ?']);
                self::assertSame(23, $where['product_id = ?']);
                if ($row['status'] === 'processing') {
                    self::assertSame('old-token', $where['lease_token = ?']);
                    self::assertArrayHasKey('lease_expires_at <= ?', $where);
                } else {
                    self::assertSame(0, $where['attempt_count > ?']);
                }
                return 1;
            }
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Unfinished Ergonode media work requires a new import.', self::callback(
                static fn (array $context): bool => $context['product_id'] === 23 && $context['reason'] !== ''
            )
        );
        self::assertSame([], $this->repository($connection, $logger)->claim(10, 900));
        $fresh = $conditions[1][0];
        self::assertStringContainsString("status = 'pending' AND attempt_count = 0", $fresh);
        self::assertStringNotContainsString('processing', $fresh);
    }

    public static function unfinished(): array
    {
        return [
            'interrupted process' => [['product_id' => 23, 'status' => 'processing', 'lease_token' => 'old-token']],
            'legacy delayed retry' => [['product_id' => 23, 'status' => 'pending', 'last_error' => 'disk full']],
        ];
    }

    public function testOnlyANewScheduleReactivatesAProductUsingCurrentData(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $conditions = [];
        $connection->method('select')->willReturn($this->select($conditions));
        $connection->method('quoteInto')->willReturnArgument(0);
        $connection->method('fetchAll')->willReturn([]);
        $connection->expects(self::once())->method('insertOnDuplicate')->willReturnCallback(
            static function ($table, $rows, $columns): int {
                self::assertSame('ergonode_media_product_work', $table);
                self::assertSame(23, $rows[0]['product_id']);
                self::assertSame('pending', $rows[0]['status']);
                self::assertSame(0, $rows[0]['attempt_count']);
                self::assertNull($rows[0]['last_error']);
                self::assertNull($rows[0]['lease_token']);
                self::assertSame(1, $rows[0]['synchronize_gallery']);
                self::assertContains('status', $columns);
                return 1;
            }
        );
        $this->repository($connection)->scheduleProducts([23], true);
    }

    public function testFreshDispatchCannotFindFailedExpiredOrDelayedRetryWork(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $conditions = [];
        $connection->method('select')->willReturn($this->select($conditions));
        $connection->method('quoteInto')->willReturnCallback(static fn ($q, $v): string => str_replace('?', "'" . $v . "'", $q));
        $connection->expects(self::once())->method('fetchOne')->willReturn(false);
        self::assertFalse($this->repository($connection)->hasWork());
        self::assertStringContainsString("status = 'pending' AND attempt_count = 0", $conditions[0][0]);
        self::assertStringNotContainsString('processing', $conditions[0][0]);
    }

    #[DataProvider('previousResults')]
    public function testUnchangedImportChecksOnlyThisProductsUnsuccessfulWork(mixed $result, bool $failed): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $conditions = [];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->expects(self::exactly(2))->method('where')->willReturnCallback(
            static function ($where, $value = null) use (&$conditions, $select): Select {
                $conditions[] = [$where, $value]; return $select;
            }
        );
        $connection->method('select')->willReturn($select);
        $connection->method('quoteInto')->willReturnCallback(static fn ($q, $v): string => str_replace('?', "'" . $v . "'", $q));
        $connection->expects(self::once())->method('fetchOne')->willReturn($result);
        self::assertSame($failed, $this->repository($connection)->hasFailedWork(23));
        self::assertSame(['product_id = ?', 23], $conditions[0]);
        self::assertStringContainsString("status = 'failed'", $conditions[1][0]);
        self::assertStringContainsString("status = 'processing'", $conditions[1][0]);
        self::assertStringContainsString('lease_expires_at <=', $conditions[1][0]);
        self::assertStringContainsString('attempt_count > 0', $conditions[1][0]);
    }

    public static function previousResults(): array
    {
        return ['completed media' => [false, false], 'failed media' => [23, true]];
    }

    private function select(array &$conditions): Select
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnCallback(static function () use (&$conditions, $select): Select {
            $conditions[] = []; return $select;
        });
        $select->method('where')->willReturnCallback(static function ($where, $value = null) use (&$conditions, $select): Select {
            $conditions[array_key_last($conditions)][] = $where; return $select;
        });
        foreach (['order', 'limit', 'forUpdate'] as $method) { $select->method($method)->willReturnSelf(); }
        return $select;
    }

    private function repository(AdapterInterface $connection, ?LoggerInterface $logger = null): MediaRepository
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtDate')->willReturn('2026-10-04 12:00:00');
        return new MediaRepository($resource, $clock, $this->createStub(File::class),
            $logger ?? $this->createStub(LoggerInterface::class));
    }
}
