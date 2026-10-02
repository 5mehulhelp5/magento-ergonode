<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Materialization;

use Ergonode\Media\Api\SharedPathStrategyInterface;
use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Index\IndexedFileResolver;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Lock\LockManagerInterface;

class AssetMaterializer
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly SourcePreparer $preparer,
        private readonly DirectoryList $directories,
        private readonly File $file,
        private readonly LockManagerInterface $locks,
        private readonly SharedPathStrategyInterface $sharedStrategy,
        private readonly ProductPathStrategy $productStrategy,
        private readonly IndexedFileResolver $indexedFiles,
        private readonly MaterializationCache $cache
    ) {
    }

    public function shared(Asset $asset, string $directory): string
    {
        return $this->materialize($asset, 'shared:' . trim($directory, '/'), null, $directory);
    }
    public function seo(Asset $asset, int $productId): string
    {
        return $this->materialize($asset, 'product:' . $productId, $productId, null);
    }

    private function materialize(Asset $asset, string $scope, ?int $productId, ?string $directory): string
    {
        $asset = $this->repository->getAsset($asset->id);
        $cached = $this->cache->get($asset, $scope);
        if ($cached !== null) {
            return $cached;
        }
        $currentPath = $this->currentPath($asset, $scope);
        if ($currentPath !== null) {
            return $this->cache->remember($asset, $scope, $currentPath);
        }
        $asset = $this->preparer->prepare($asset);
        if ($asset->contentHash === null || $asset->cachePath === null) {
            throw new LocalizedException(__('Prepared Ergonode media has no content identity.'));
        }
        $currentPath = $this->currentPath($asset, $scope);
        if ($currentPath !== null) {
            return $this->cache->remember($asset, $scope, $currentPath);
        }
        $path = $productId === null
            ? $this->sharedStrategy->resolve(
                $asset->sourcePath,
                $asset->contentHash,
                $asset->extension,
                (string)$directory
            )
            : $this->productStrategy->resolve($asset, $productId);
        $lock = 'ergonode_media_local_' . ($productId === null
            ? bin2hex($asset->contentHash) : hash('sha256', $path));
        if (!$this->locks->lock($lock, 60)) {
            throw new LocalizedException(__('Magento media path is being materialized.'));
        }
        try {
            if ($productId === null) {
                $existing = $this->cache->getShared($asset->contentHash)
                    ?? $this->indexedFiles->find($asset->contentHash);
                if ($existing !== null) {
                    $this->repository->saveMaterialization($asset, $scope, null, $existing);
                    return $this->cache->remember($asset, $scope, $existing, true);
                }
            }
            $target = $this->mediaPath($path);
            if (!$this->file->fileExists($target)) {
                $this->file->checkAndCreateFolder($this->file->dirname($target));
                $temporary = $this->file->dirname($target) . '/.ergonode-' . bin2hex(random_bytes(8)) . '.tmp';
                try {
                    if (!$this->file->cp($this->varPath($asset->cachePath), $temporary)
                        || !$this->file->mv($temporary, $target)
                    ) {
                        throw new LocalizedException(__('Unable to publish the local media file.'));
                    }
                } finally {
                    if ($this->file->fileExists($temporary)) {
                        $this->file->rm($temporary);
                    }
                }
            }
            $this->indexedFiles->remember($path, $asset->contentHash);
            $this->repository->saveMaterialization($asset, $scope, $productId, $path);
        } finally {
            $this->locks->unlock($lock);
        }
        return $this->cache->remember($asset, $scope, $path, true);
    }

    private function currentPath(Asset $asset, string $scope): ?string
    {
        if ($asset->status !== 'active' || $asset->contentHash === null) {
            return null;
        }
        $current = $this->repository->materialization($asset->id, $scope);
        if ($current === null
            || $current['revision'] !== $asset->revision
            || !hash_equals($current['content_hash'], $asset->contentHash)
            || !$this->existsMedia($current['path'])
        ) {
            return null;
        }

        return $current['path'];
    }

    private function varPath(string $p): string
    {
        return rtrim($this->directories->getPath(DirectoryList::VAR_DIR), '/') . '/' . ltrim($p, '/');
    }
    private function mediaPath(string $p): string
    {
        return rtrim($this->directories->getPath(DirectoryList::MEDIA), '/') . '/' . ltrim($p, '/');
    }

    private function existsMedia(string $p): bool
    {
        return $this->file->fileExists($this->mediaPath($p));
    }
}
