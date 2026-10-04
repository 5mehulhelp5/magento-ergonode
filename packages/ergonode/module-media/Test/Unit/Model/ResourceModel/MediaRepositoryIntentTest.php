<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\ResourceModel;

use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MediaRepositoryIntentTest extends TestCase
{
    public function testStreamRequestsGalleryOnlyForProductsUsingTheChangedAssetInTheirGallery(): void
    {
        $connection = $this->createStub(AdapterInterface::class);
        $select = $this->createStub(Select::class);
        foreach (['from', 'where', 'forUpdate'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchCol')->willReturnOnConsecutiveCalls([23], [23, 42]);
        $connection->method('quoteInto')->willReturnArgument(0);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $repository = $this->getMockBuilder(MediaRepository::class)->setConstructorArgs([
            $resource, $this->createStub(DateTime::class), $this->createStub(File::class), $this->createStub(LoggerInterface::class),
        ])->onlyMethods(['ensureAsset', 'scheduleProducts'])->getMock();
        $repository->method('ensureAsset')->willReturn(new \Ergonode\Media\Model\Data\Asset(
            1, 'photo.jpg', null, 'Photo', 'jpg', 'image/jpeg', null, null, 1, 'pending'
        ));
        $scheduled = [];
        $repository->expects(self::exactly(2))->method('scheduleProducts')->willReturnCallback(
            static function (array $ids, bool $gallery = false) use (&$scheduled): void { $scheduled[] = [$ids, $gallery]; }
        );
        self::assertSame([23, 42], $repository->recordStream([[
            'cursor' => 'c1', 'path' => 'photo.jpg', 'url' => 'https://example.test/photo.jpg',
            'name' => 'Photo', 'extension' => 'jpg', 'mime' => 'image/jpeg', 'size' => 12,
        ]]));
        self::assertSame([[[23], true], [[42], false]], $scheduled);
    }

    public function testFileOnlyScheduleCannotCancelPendingGalleryAndExplicitEmptyGalleryRequestsIt(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $calls = [];
        $connection->expects(self::exactly(2))->method('insertOnDuplicate')->willReturnCallback(
            static function (string $table, array $rows, array $columns) use (&$calls): int {
                $calls[] = [$table, $rows[0]['synchronize_gallery'], $columns];
                return 1;
            }
        );
        $select = $this->createStub(Select::class);
        foreach (['from', 'where'] as $method) { $select->method($method)->willReturnSelf(); }
        $connection->method('select')->willReturn($select);
        $connection->method('quoteInto')->willReturnArgument(0);
        $connection->method('fetchAll')->willReturn([]);
        $repository = $this->repository($connection);
        $repository->replaceGallery(23, []);
        $repository->scheduleProducts([23]);
        self::assertSame('ergonode_media_product_work', $calls[0][0]);
        self::assertSame(1, $calls[0][1]);
        self::assertContains('synchronize_gallery', $calls[0][2]);
        self::assertSame(0, $calls[1][1]);
        self::assertNotContains('synchronize_gallery', $calls[1][2]);
    }

    public function testScopedReplacementAndCleanupDoNotDeletePreservedImageUsages(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createStub(Select::class);
        foreach (['from', 'where'] as $method) { $select->method($method)->willReturnSelf(); }
        $connection->method('select')->willReturn($select);
        $connection->method('quoteInto')->willReturnArgument(0);
        $connection->method('fetchAll')->willReturn([]);
        $connection->expects(self::once())->method('update')->with('ergonode_media_file_usage', ['desired' => 0], [
            'product_id = ?' => 23,
            'attribute_code IN (?)' => ['manual', 'image'],
            'attribute_code NOT IN (?)' => ['image'],
        ]);
        $connection->expects(self::once())->method('delete')->with('ergonode_media_file_usage', [
            'product_id = ?' => 23, 'desired = ?' => 0, 'attribute_code NOT IN (?)' => ['image'],
        ]);
        $repository = $this->repository($connection);
        $repository->replaceFileUsages(23, [], ['manual', 'image'], ['image']);
        $repository->purgeObsoleteFileUsages(23, ['image']);
    }

    public function testObsoleteImageReferenceIsRemovedOnlyForItsProductRoleStoreAndSource(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete')->with('ergonode_media_file_usage', [
            'product_id = ?' => 23, 'attribute_code = ?' => 'packshot', 'store_id = ?' => 2,
            'source_path_hash = ?' => hash('sha256', 'b.jpg', true),
        ]);
        $this->repository($connection)->removeFileUsage(23, 'packshot', 2, 'b.jpg');
    }

    #[DataProvider('claimIntents')]
    public function testFreshClaimPreservesExplicitIntentAndLegacyGallerySelection(
        ?int $intent,
        bool $legacyHasGallery,
        bool $expected
    ): void {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createStub(Select::class);
        foreach (['from', 'where', 'order', 'limit', 'forUpdate'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $connection->method('select')->willReturn($select);
        $connection->method('quoteInto')->willReturnArgument(0);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls([], [[
            'product_id' => 23, 'attempt_count' => 0, 'synchronize_gallery' => $intent,
        ]]);
        $connection->expects($intent === null ? self::once() : self::never())->method('fetchOne')
            ->willReturn($legacyHasGallery ? '23' : false);
        $work = $this->repository($connection)->claim(10, 120)[0];
        self::assertSame($expected, $work->synchronizeGallery);
        self::assertSame(1, $work->attemptCount);
    }

    public static function claimIntents(): array
    {
        return [
            'fresh file-only work' => [0, true, false],
            'explicit gallery including empty selection' => [1, false, true],
            'legacy managed gallery' => [null, true, true],
            'legacy file-only product' => [null, false, false],
        ];
    }

    private function repository(AdapterInterface $connection): MediaRepository
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtDate')->willReturn('2026-10-02 12:00:00');
        return new MediaRepository($resource, $clock, $this->createStub(File::class), $this->createStub(LoggerInterface::class));
    }
}
