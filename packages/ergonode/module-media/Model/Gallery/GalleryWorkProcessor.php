<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Gallery;

use Ergonode\Media\Api\GalleryModeLockInterface;
use Ergonode\Media\Model\Config\GalleryModeProvider;
use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\Materialization\AssetMaterializer;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\ProductMedia\Api\GallerySynchronizerInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Magento\Framework\Exception\LocalizedException;

class GalleryWorkProcessor
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly GalleryModeProvider $modeProvider,
        private readonly GalleryModeLockInterface $modeLock,
        private readonly AssetMaterializer $materializer,
        private readonly GallerySynchronizerInterface $gallery,
        private readonly ImageRolesInterface $imageRoles
    ) {
    }
    public function process(int $productId): void
    {
        $mode = $this->modeProvider->get();
        $desired = [];
        $managed = [];
        $resolved = [];
        $attachments = [];
        foreach ($this->repository->galleryUsages($productId) as $usage) {
            if ($usage['attached_path'] !== null) {
                $managed[] = $usage['attached_path'];
            }
            if (!$usage['desired']) {
                continue;
            }
            $asset = $usage['asset'];
            $path = $mode === MediaConfig::MODE_SEO
                ? $this->materializer->seo($asset, $productId)
                : $this->materializer->shared($asset, 'catalog/product/ergonode/shared');
            $desired[] = ['path' => $path, 'position' => $usage['position']];
            $resolved[$asset->sourcePath] = $path;
            $attachments[$asset->id] = $path;
        }
        $mappedRoles = [];
        $roles = $this->imageRoles->getOptions();
        foreach ($this->repository->fileUsages($productId) as $usage) {
            if (!isset($roles[$usage['attribute_code']])) {
                continue;
            }
            $path = $usage['desired'] ? ($resolved[$usage['source_path']] ?? null) : null;
            if ($usage['desired'] && $path === null) {
                throw new LocalizedException(__('Configure a gallery position for every mapped Image attribute.'));
            }
            $mappedRoles[] = [
                'attribute' => $usage['attribute_code'],
                'store_id' => (int)$usage['store_id'],
                'path' => $path,
            ];
        }
        $this->gallery->synchronize($productId, $desired, array_values(array_unique($managed)), $mappedRoles);
        foreach ($attachments as $assetId => $path) {
            $this->repository->saveGalleryPath($productId, $assetId, $path);
        }
        if ($desired !== []) {
            $this->modeLock->lock($mode);
        }
        $this->repository->purgeObsoleteGalleryUsages($productId);
    }
}
