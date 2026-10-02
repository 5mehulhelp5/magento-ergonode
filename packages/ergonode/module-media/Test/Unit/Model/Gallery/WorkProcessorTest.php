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
        $gallery->expects(self::never())->method('process');
        $configuration = $this->createStub(GalleryConfigurationInterface::class);
        $configuration->method('isSynchronizationEnabled')->willReturn(false);
        (new WorkProcessor(
            $repository,
            $gallery,
            $this->createStub(SharedAssetResolverInterface::class),
            $this->createStub(FileAttributeWriterInterface::class),
            $configuration,
            $this->createStub(ImageRolesInterface::class)
        ))->process(new WorkItem(23, 'lease-token', 1));
    }
}
