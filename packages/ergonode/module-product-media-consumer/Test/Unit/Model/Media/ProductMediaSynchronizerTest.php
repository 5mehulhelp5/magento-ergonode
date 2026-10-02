<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Test\Unit\Model\Media;

use Ergonode\Media\Api\GallerySchedulerInterface;
use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;
use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;
use Ergonode\ProductMediaConsumer\Model\Media\ProductGallerySelectionProvider;
use Ergonode\ProductMediaConsumer\Model\Media\ProductMediaSynchronizer;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use PHPUnit\Framework\TestCase;

class ProductMediaSynchronizerTest extends TestCase
{
    public function testRecordsFilesAndSchedulesGallery(): void
    {
        $attributes = [];
        $source = new RemoteProduct('SKU-1', 'simple', 'template', false, [], $attributes);
        $files = $this->createMock(ProductFileUsageSynchronizer::class);
        $files->expects(self::once())->method('synchronize')->with(23, $attributes);
        $selection = new GallerySelection(['/image.jpg']);
        $provider = $this->createMock(ProductGallerySelectionProvider::class);
        $provider->expects(self::once())->method('provide')->with($attributes)->willReturn($selection);
        $scheduler = $this->createMock(GallerySchedulerInterface::class);
        $scheduler->expects(self::once())->method('schedule')->with(23, $selection);

        (new ProductMediaSynchronizer($files, $provider, $scheduler))->synchronize(23, 'SKU-1', $source);
    }
}
