<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Gallery;

use Ergonode\Media\Api\GalleryModeLockInterface;
use Ergonode\Media\Model\Config\GalleryModeProvider;
use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Gallery\GalleryWorkProcessor;
use Ergonode\Media\Model\Materialization\AssetMaterializer;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\ProductMedia\Api\GallerySynchronizerInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GalleryWorkProcessorTest extends TestCase
{
    public function testFailedDownloadKeepsAttachmentsAndRolesForRetry(): void
    {
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $asset = new Asset(1, 'photo', null, 'photo', 'jpg', 'image/jpeg', null, null, 1, 'pending');
        $repository->method('galleryUsages')->willReturn([['asset' => $asset,
            'attached_path' => 'catalog/product/old.jpg', 'desired' => true, 'position' => 1]]);
        $repository->expects(self::never())->method('saveGalleryPath');
        $repository->expects(self::never())->method('purgeObsoleteGalleryUsages');
        $materializer = $this->createMock(AssetMaterializer::class);
        $materializer->expects(self::once())->method('shared')
            ->willThrowException(new RuntimeException('download failed'));
        $gallery = $this->createMock(GallerySynchronizerInterface::class);
        $gallery->expects(self::never())->method('synchronize');
        $mode = $this->createStub(GalleryModeProvider::class);
        $mode->method('get')->willReturn('shared');
        $this->expectException(RuntimeException::class);
        (new GalleryWorkProcessor(
            $repository,
            $mode,
            $this->createStub(GalleryModeLockInterface::class),
            $materializer,
            $gallery,
            $this->createStub(ImageRolesInterface::class)
        ))->process(5);
    }
}
