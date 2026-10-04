<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Media;

use Ergonode\Media\Api\GallerySchedulerInterface;
use Ergonode\ProductConsumer\Api\UnchangedProductStateSynchronizerInterface;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\ProductConsumer\Api\SelectedProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Ergonode\ProductMediaConsumer\Model\Magento\ProductFileUsageSynchronizer;

class ProductMediaSynchronizer implements UnchangedProductStateSynchronizerInterface, SelectedProductStateSynchronizerInterface
{
    public function __construct(
        private readonly ProductFileUsageSynchronizer $fileUsageSynchronizer,
        private readonly ProductGallerySelectionProvider $gallerySelectionProvider,
        private readonly GallerySchedulerInterface $galleryScheduler,
        private readonly MediaRepositoryInterface $repository,
        private readonly ?\Ergonode\ProductConsumer\Model\Pipeline\BatchScope $batchScope = null
    ) {
    }

    public function synchronize(int $productId, string $magentoSku, RemoteProduct $source): void
    {
        unset($magentoSku);
        $this->synchronizeMedia($productId, $source, null);
    }

    public function synchronizeSelected(int $productId, string $magentoSku, RemoteProduct $source, array $attributeCodes): void
    {
        unset($magentoSku);
        $this->synchronizeMedia($productId, $source, $attributeCodes);
    }

    public function synchronizeUnchanged(int $productId, string $magentoSku, RemoteProduct $source): void
    {
        if ($this->repository->hasFailedWork($productId)) {
            $this->synchronize($productId, $magentoSku, $source);
        }
    }

    private function synchronizeMedia(int $productId, RemoteProduct $source, ?array $attributeCodes): void
    {
        $context = $this->batchScope?->get();
        if ($context !== null) {
            $context->media[$productId] = fn() => $this->writeMediaIntent($productId, $source, $attributeCodes);
            return;
        }
        $this->writeMediaIntent($productId, $source, $attributeCodes);
    }

    private function writeMediaIntent(int $productId, RemoteProduct $source, ?array $attributeCodes): void
    {
        $selection = $this->gallerySelectionProvider->provide($source->attributes);
        $this->fileUsageSynchronizer->synchronize($productId, $source->attributes, $selection !== null, $attributeCodes);
        $this->galleryScheduler->schedule(
            $productId,
            $selection
        );
    }
}
