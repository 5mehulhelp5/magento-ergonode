<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\Media\Api\GallerySchedulerInterface;
use Ergonode\Media\Model\Queue\QueuePublisher;
use Ergonode\Media\Model\ResourceModel\MediaRepository;
use Ergonode\Media\Model\ValueObject\Gallery\GallerySelection;

class GalleryScheduler implements GallerySchedulerInterface
{
    public function __construct(
        private readonly GalleryConfigurationInterface $config,
        private readonly MediaRepository $repository,
        private readonly QueuePublisher $publisher
    ) {
    }

    public function schedule(int $productId, ?GallerySelection $selection): void
    {
        if (!$this->config->isSynchronizationEnabled() || $selection === null) {
            return;
        }
        $this->repository->replaceGallery($productId, $selection->paths());
        $this->publisher->dispatch();
    }
}
