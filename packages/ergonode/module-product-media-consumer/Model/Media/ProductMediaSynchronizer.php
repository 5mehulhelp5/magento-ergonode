<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Media;

use Ergonode\Media\Api\GallerySchedulerInterface;
use Ergonode\ProductConsumer\Api\ProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;

class ProductMediaSynchronizer implements ProductStateSynchronizerInterface
{
    public function __construct(
        private readonly ProductFileUsageSynchronizer $fileUsageSynchronizer,
        private readonly ProductGallerySelectionProvider $gallerySelectionProvider,
        private readonly GallerySchedulerInterface $galleryScheduler
    ) {
    }

    public function synchronize(int $productId, string $magentoSku, RemoteProduct $source): void
    {
        unset($magentoSku);
        $selection = $this->gallerySelectionProvider->provide($source->attributes);
        $this->fileUsageSynchronizer->synchronize($productId, $source->attributes);
        $this->galleryScheduler->schedule(
            $productId,
            $selection
        );
    }
}
