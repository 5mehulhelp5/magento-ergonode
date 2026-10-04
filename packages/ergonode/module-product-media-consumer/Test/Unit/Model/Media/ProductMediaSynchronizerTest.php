<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\Media\Api\GallerySchedulerInterface;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;
use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;
use Ergonode\ProductMediaConsumer\Model\Media\ProductGallerySelectionProvider;
use Ergonode\ProductMediaConsumer\Model\Media\ProductMediaSynchronizer;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ProductMediaSynchronizerTest extends TestCase
{
    public function testRecordsFilesAndSchedulesGallery(): void
    {
        $attributes = [];
        $source = new RemoteProduct('SKU-1', 'simple', 'template', false, [], $attributes);
        $files = $this->createMock(ProductFileUsageSynchronizer::class);
        $files->expects(self::once())->method('synchronize')->with(23, $attributes, true, null);
        $selection = new GallerySelection(['/image.jpg']);
        $provider = $this->createMock(ProductGallerySelectionProvider::class);
        $provider->expects(self::once())->method('provide')->with($attributes)->willReturn($selection);
        $scheduler = $this->createMock(GallerySchedulerInterface::class);
        $scheduler->expects(self::once())->method('schedule')->with(23, $selection);

        (new ProductMediaSynchronizer($files, $provider, $scheduler, $this->createStub(MediaRepositoryInterface::class)))->synchronize(23, 'SKU-1', $source);
    }

    public function testMissingGallerySkipsImageSynchronizationForBothImportVariants(): void
    {
        $source = new RemoteProduct('SKU-1', 'simple', 'template', false, [], []);
        $calls = [];
        $files = $this->createMock(ProductFileUsageSynchronizer::class);
        $files->expects(self::exactly(2))->method('synchronize')->willReturnCallback(
            static function (int $id, array $attributes, bool $images, ?array $codes) use (&$calls): void {
                $calls[] = [$id, $images, $codes];
            }
        );
        $provider = $this->createStub(ProductGallerySelectionProvider::class);
        $provider->method('provide')->willReturn(null);
        $synchronizer = new ProductMediaSynchronizer($files, $provider, $this->createStub(GallerySchedulerInterface::class), $this->createStub(MediaRepositoryInterface::class));
        $synchronizer->synchronize(23, 'SKU-1', $source);
        $synchronizer->synchronizeSelected(23, 'SKU-1', $source, ['manual', 'image']);
        self::assertSame([[23, false, null], [23, false, ['manual', 'image']]], $calls);
    }

    public function testSelectedImportUsesTheSameGalleryAndExplicitEmptySelectionStillUpdatesImages(): void
    {
        $source = new RemoteProduct('SKU-1', 'simple', 'template', false, [], []);
        $files = $this->createMock(ProductFileUsageSynchronizer::class);
        $files->expects(self::once())->method('synchronize')->with(23, [], true, ['manual', 'hover_image']);
        $selection = new GallerySelection([]);
        $provider = $this->createStub(ProductGallerySelectionProvider::class);
        $provider->method('provide')->willReturn($selection);
        $scheduler = $this->createMock(GallerySchedulerInterface::class);
        $scheduler->expects(self::once())->method('schedule')->with(23, $selection);
        (new ProductMediaSynchronizer($files, $provider, $scheduler, $this->createStub(MediaRepositoryInterface::class)))
            ->synchronizeSelected(23, 'SKU-1', $source, ['manual', 'hover_image']);
    }
    #[DataProvider('previousOutcomes')]
    public function testUnchangedSourceIsOnlyScheduledAgainAfterFailedMedia(bool $failed): void
    {
        $source = new RemoteProduct('SKU-1', 'simple', 'template', false, [], []);
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->expects(self::once())->method('hasFailedWork')->with(23)->willReturn($failed);
        $files = $this->createMock(ProductFileUsageSynchronizer::class);
        $files->expects($failed ? self::once() : self::never())->method('synchronize')->with(23, [], true, null);
        $selection = new GallerySelection(['current-a.jpg', 'current-c.jpg']);
        $provider = $this->createMock(ProductGallerySelectionProvider::class);
        $provider->expects($failed ? self::once() : self::never())->method('provide')->willReturn($selection);
        $scheduler = $this->createMock(GallerySchedulerInterface::class);
        $scheduler->expects($failed ? self::once() : self::never())->method('schedule')->with(23, $selection);
        (new ProductMediaSynchronizer($files, $provider, $scheduler, $repository))
            ->synchronizeUnchanged(23, 'SKU-1', $source);
    }

    public static function previousOutcomes(): array
    {
        return ['successful media remain skipped' => [false], 'failed media use the new pass source' => [true]];
    }

}
