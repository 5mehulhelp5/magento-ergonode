<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryConfigurationInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Api\GalleryWriteLockInterface;
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
        private readonly ImageRolesInterface $imageRoles,
        private readonly GalleryWriteLockInterface $galleryLocks
    ) {
    }
    public function process(WorkItem $work): void
    {
        $galleryEnabled = $this->galleryConfiguration->isSynchronizationEnabled();
        $updateGallery = $galleryEnabled && $work->synchronizeGallery;
        $writeGallery = $updateGallery ? $this->gallery->prepare($work->productId) : null;
        $writes = [];
        $roles = $this->imageRoles->getOptions();
        foreach ($this->repository->fileUsages($work->productId) as $usage) {
            if (isset($roles[$usage['attribute_code']])) {
                continue;
            }
            if (!$usage['desired']) {
                $writes[] = ['usage' => $usage, 'path' => null];
                continue;
            }
            $path = $this->sharedResolver->resolve($usage['source_path'], 'catalog/product/ergonode/shared');
            if ($path !== $usage['attached_path']) {
                $writes[] = ['usage' => $usage, 'path' => $path];
            }
        }
        $write = function () use ($work, $writes, $writeGallery, $updateGallery, $roles): void {
            if ($writeGallery !== null) {
                $writeGallery();
            }
            foreach ($writes as ['usage' => $usage, 'path' => $path]) {
                if ($path === null) {
                    $this->fileWriter->clear($work->productId, $usage['attribute_code'], $usage['store_id']);
                } else {
                    $this->fileWriter->write($work->productId, $usage['attribute_code'], $usage['store_id'], $path);
                    $this->repository->saveFilePath($work->productId, $usage['attribute_code'], $usage['store_id'], $path);
                }
            }
            $this->repository->purgeObsoleteFileUsages($work->productId, $updateGallery ? [] : array_keys($roles));
        };
        $this->galleryLocks->run(fn(): bool => $this->repository->applyWork($work, $write));
    }
}
