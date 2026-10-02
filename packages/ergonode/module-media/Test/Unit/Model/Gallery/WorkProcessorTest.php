<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\Media\Api\SharedAssetResolverInterface;
use Ergonode\Media\Model\Data\WorkItem;
use Ergonode\Media\Model\Gallery\GalleryWorkProcessor;
use Ergonode\Media\Model\Gallery\WorkProcessor;
use Ergonode\Media\Model\Port\FileAttributeWriterInterface;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use PHPUnit\Framework\TestCase;

class WorkProcessorTest extends TestCase
{
    public function testDisabledGalleryKeepsItsPendingUsagesAndProcessesOnlyFiles(): void
    {
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->expects(self::once())->method('fileUsages')->with(23)->willReturn([]);
        $repository->expects(self::never())->method('purgeObsoleteFileUsages');
        $gallery = $this->createMock(GalleryWorkProcessor::class);
        $gallery->expects(self::never())->method('prepare');
        $repository->expects(self::once())->method('applyWork')->willReturnCallback(
            static function (WorkItem $item, callable $write): bool { $write(); return true; }
        );
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(false);
        (new WorkProcessor(
            $repository,
            $gallery,
            $this->createStub(SharedAssetResolverInterface::class),
            $this->createStub(FileAttributeWriterInterface::class),
            $configuration,
            $this->createStub(ImageRolesInterface::class),
            new \Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks(
                $this->createStub(\Magento\Framework\Lock\LockManagerInterface::class))
        ))->process(new WorkItem(23, 'lease-token', 1));
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('writePermission')]
    public function testPreparedFilesAndGalleryAreWrittenOnlyByCurrentWorker(bool $allowed): void
    {
        $events = [];
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('fileUsages')->willReturn([[
            'source_path' => 'manual.pdf', 'attribute_code' => 'manual', 'store_id' => 2,
            'desired' => true, 'attached_path' => 'old.pdf',
        ]]);
        $repository->expects($allowed ? self::once() : self::never())->method('saveFilePath')
            ->with(23, 'manual', 2, 'new.pdf');
        $repository->expects($allowed ? self::once() : self::never())->method('purgeObsoleteFileUsages');
        $repository->expects(self::once())->method('applyWork')->willReturnCallback(
            static function (WorkItem $item, callable $write) use ($allowed, &$events): bool {
                $events[] = 'guard';
                if ($allowed) { $write(); }
                return $allowed;
            }
        );
        $gallery = $this->createMock(GalleryWorkProcessor::class);
        $gallery->expects(self::once())->method('prepare')->willReturnCallback(
            static function () use (&$events): \Closure {
                $events[] = 'prepare gallery';
                return static function () use (&$events): void { $events[] = 'write gallery'; };
            }
        );
        $resolver = $this->createMock(SharedAssetResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturnCallback(
            static function () use (&$events): string { $events[] = 'download file'; return 'new.pdf'; }
        );
        $writer = $this->createMock(FileAttributeWriterInterface::class);
        $writer->expects($allowed ? self::once() : self::never())->method('write')
            ->with(23, 'manual', 2, 'new.pdf')->willReturnCallback(
                static function () use (&$events): void { $events[] = 'write file'; }
            );
        $writer->expects(self::never())->method('clear');
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        (new WorkProcessor($repository, $gallery, $resolver, $writer, $configuration,
            $this->createStub(ImageRolesInterface::class),
            new \Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks(
                $this->createStub(\Magento\Framework\Lock\LockManagerInterface::class))))->process(new WorkItem(23, 'lease', 1));
        self::assertSame($allowed
            ? ['prepare gallery', 'download file', 'guard', 'write gallery', 'write file']
            : ['prepare gallery', 'download file', 'guard'], $events);
    }

    public static function writePermission(): array
    {
        return ['current' => [true], 'rescheduled while downloading' => [false]];
    }

}
