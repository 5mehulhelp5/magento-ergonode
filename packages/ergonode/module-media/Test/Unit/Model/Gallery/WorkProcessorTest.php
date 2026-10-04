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
    #[\PHPUnit\Framework\Attributes\DataProvider('galleryIntent')]
    public function testActualGalleryAndRolePipelineDistinguishesNoUpdateFromExplicitEmptyGallery(bool $requested): void
    {
        $repository = $this->createStub(MediaRepositoryInterface::class);
        $repository->method('galleryUsages')->willReturn([]);
        $repository->method('fileUsages')->willReturn([]);
        $repository->method('applyWork')->willReturnCallback(
            static function (WorkItem $item, callable $write): bool { $write(); return true; }
        );
        $events = [];
        $galleryWriter = $this->createMock(\Ergonode\ProductMedia\Model\Port\GalleryWriterInterface::class);
        $galleryWriter->expects($requested ? self::once() : self::never())->method('synchronize')->with(23, [], [])
            ->willReturnCallback(static function () use (&$events): array { $events[] = 'gallery'; return []; });
        $roleWriter = $this->createMock(\Ergonode\ProductMedia\Model\Port\RoleWriterInterface::class);
        $roleWriter->expects($requested ? self::once() : self::never())->method('write')->with(23, 0,
            ['image' => null, 'small_image' => null, 'thumbnail' => null])
            ->willReturnCallback(static function () use (&$events): void { $events[] = 'image roles'; });
        $imageRoles = $this->createStub(ImageRolesInterface::class);
        $synchronizer = new \Ergonode\ProductMedia\Model\Gallery\GallerySynchronizer($galleryWriter,
            $this->createStub(\Ergonode\ProductMedia\Api\GalleryRulesInterface::class), $roleWriter, $imageRoles);
        $mode = $this->createStub(\Ergonode\Media\Model\Config\GalleryModeProvider::class);
        $mode->method('get')->willReturn('shared');
        $gallery = new GalleryWorkProcessor($repository, $mode,
            $this->createStub(\Ergonode\Media\Api\GalleryModeLockInterface::class),
            $this->createStub(\Ergonode\Media\Model\Materialization\AssetMaterializer::class), $synchronizer, $imageRoles);
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        (new WorkProcessor($repository, $gallery, $this->createStub(SharedAssetResolverInterface::class),
            $this->createStub(FileAttributeWriterInterface::class), $configuration, $imageRoles,
            new \Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks(
                $this->createStub(\Magento\Framework\Lock\LockManagerInterface::class))))
            ->process(new WorkItem(23, 'lease', 1, $requested));
        self::assertSame($requested ? ['gallery', 'image roles'] : [], $events);
    }

    public static function galleryIntent(): array
    {
        return ['missing gallery must leave native roles untouched' => [false], 'explicit empty gallery clears roles afterwards' => [true]];
    }

    public function testDisabledGalleryKeepsItsPendingUsagesAndProcessesOnlyFiles(): void
    {
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->expects(self::once())->method('fileUsages')->with(23)->willReturn([]);
        $repository->expects(self::once())->method('purgeObsoleteFileUsages')->with(23, []);
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
                $this->createStub(\Magento\Framework\Lock\LockManagerInterface::class))))->process(new WorkItem(23, 'lease', 1, true));
        self::assertSame($allowed
            ? ['prepare gallery', 'download file', 'guard', 'write gallery', 'write file']
            : ['prepare gallery', 'download file', 'guard'], $events);
    }

    public static function writePermission(): array
    {
        return ['current' => [true], 'rescheduled while downloading' => [false]];
    }

    public function testFileOnlyWorkPreservesGalleryAndImageRolesEvenWhenGalleryIsEnabled(): void
    {
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('fileUsages')->willReturn([
            ['source_path' => 'photo.jpg', 'attribute_code' => 'image', 'store_id' => 0,
                'desired' => false, 'attached_path' => 'old.jpg'],
            ['source_path' => 'manual.pdf', 'attribute_code' => 'manual', 'store_id' => 0,
                'desired' => true, 'attached_path' => null],
        ]);
        $repository->method('applyWork')->willReturnCallback(
            static function (WorkItem $item, callable $write): bool { $write(); return true; }
        );
        $repository->expects(self::once())->method('purgeObsoleteFileUsages')
            ->with(23, ['image', 'small_image', 'thumbnail']);
        $gallery = $this->createMock(GalleryWorkProcessor::class);
        $gallery->expects(self::never())->method('prepare');
        $resolver = $this->createMock(SharedAssetResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with('manual.pdf', 'catalog/product/ergonode/shared')
            ->willReturn('manual.pdf');
        $writer = $this->createMock(FileAttributeWriterInterface::class);
        $writer->expects(self::never())->method('clear');
        $writer->expects(self::once())->method('write')->with(23, 'manual', 0, 'manual.pdf');
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(true);
        $roles = $this->createStub(ImageRolesInterface::class);
        $roles->method('getOptions')->willReturn(['image' => 'Image', 'small_image' => 'Small', 'thumbnail' => 'Thumbnail']);
        (new WorkProcessor($repository, $gallery, $resolver, $writer, $configuration, $roles,
            new \Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks(
                $this->createStub(\Magento\Framework\Lock\LockManagerInterface::class))))
            ->process(new WorkItem(23, 'lease', 1, false));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fileClearResults')]
    public function testDisabledGalleryRetiresClearedFileUsageAndPreservesImageRoles(bool $clearFails): void
    {
        $usages = [
            ['source_path' => 'old.pdf', 'attribute_code' => 'manual', 'store_id' => 2,
                'desired' => false, 'attached_path' => 'catalog/product/old.pdf'],
            ['source_path' => 'old.jpg', 'attribute_code' => 'image', 'store_id' => 0,
                'desired' => false, 'attached_path' => 'catalog/product/old.jpg'],
        ];
        $manual = 'catalog/product/old.pdf';
        $events = [];
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('fileUsages')->willReturnCallback(static function () use (&$usages): array { return $usages; });
        $repository->method('applyWork')->willReturnCallback(static function ($work, $write): bool { $write(); return true; });
        $repository->expects($clearFails ? self::never() : self::exactly(2))->method('purgeObsoleteFileUsages')
            ->willReturnCallback(static function ($id, $preserved) use (&$usages, &$events): void {
                self::assertSame(23, $id);
                self::assertSame(['image'], $preserved);
                $events[] = 'purge';
                $usages = array_values(array_filter($usages, static fn ($u): bool =>
                    $u['desired'] || in_array($u['attribute_code'], $preserved, true)));
            });
        $writer = $this->createMock(FileAttributeWriterInterface::class);
        $writer->expects(self::once())->method('clear')->with(23, 'manual', 2)->willReturnCallback(
            static function () use (&$manual, &$events, $clearFails): void {
                if ($clearFails) { throw new \RuntimeException('Failed PDF clear'); }
                $manual = null;
                $events[] = 'clear';
            }
        );
        $writer->expects(self::never())->method('write');
        $gallery = $this->createMock(GalleryWorkProcessor::class);
        $gallery->expects(self::never())->method('prepare');
        $resolver = $this->createMock(SharedAssetResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(false);
        $roles = $this->createStub(ImageRolesInterface::class);
        $roles->method('getOptions')->willReturn(['image' => 'Image']);
        $processor = new WorkProcessor($repository, $gallery, $resolver, $writer, $configuration, $roles,
            new \Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks(
                $this->createStub(\Magento\Framework\Lock\LockManagerInterface::class)));
        $work = new WorkItem(23, 'lease', 1, false);
        if ($clearFails) {
            try { $processor->process($work); self::fail('Clear should fail.'); }
            catch (\RuntimeException $e) { self::assertSame('Failed PDF clear', $e->getMessage()); }
            self::assertCount(2, $usages);
            self::assertSame('catalog/product/old.pdf', $manual);
        } else {
            $processor->process($work);
            self::assertSame(['clear', 'purge'], $events);
            self::assertCount(1, $usages);
            self::assertSame('image', $usages[0]['attribute_code']);
            $manual = 'manually-added-new.pdf';
            $processor->process($work);
            self::assertSame('manually-added-new.pdf', $manual);
            self::assertSame(['clear', 'purge', 'purge'], $events);
        }
    }

    public static function fileClearResults(): array
    {
        return ['successful clear removes its reference' => [false], 'failed clear retains its reference' => [true]];
    }

}
