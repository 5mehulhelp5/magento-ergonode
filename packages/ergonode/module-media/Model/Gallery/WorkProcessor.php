<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\Media\Api\SharedAssetResolverInterface;
use Ergonode\Media\Model\Data\WorkItem;
use Ergonode\Media\Model\Port\FileAttributeWriterInterface;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;

class WorkProcessor
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly GalleryWorkProcessor $gallery,
        private readonly SharedAssetResolverInterface $sharedResolver,
        private readonly FileAttributeWriterInterface $fileWriter,
        private readonly GalleryConfigurationInterface $galleryConfiguration,
        private readonly ImageRolesInterface $imageRoles
    ) {
    }
    public function process(WorkItem $work): void
    {
        if ($this->galleryConfiguration->isSynchronizationEnabled()) {
            $this->gallery->process($work->productId);
        }
        $roles = $this->imageRoles->getOptions();
        foreach ($this->repository->fileUsages($work->productId) as $usage) {
            if (isset($roles[$usage['attribute_code']])) {
                continue;
            }
            if (!$usage['desired']) {
                $this->fileWriter->clear($work->productId, $usage['attribute_code'], $usage['store_id']);
                continue;
            }
            $path = $this->sharedResolver->resolve($usage['source_path'], 'catalog/product/ergonode/shared');
            if ($path !== $usage['attached_path']) {
                $this->fileWriter->write($work->productId, $usage['attribute_code'], $usage['store_id'], $path);
                $this->repository->saveFilePath($work->productId, $usage['attribute_code'], $usage['store_id'], $path);
            }
        }
        if ($this->galleryConfiguration->isSynchronizationEnabled()) {
            $this->repository->purgeObsoleteFileUsages($work->productId);
        }
    }
}
