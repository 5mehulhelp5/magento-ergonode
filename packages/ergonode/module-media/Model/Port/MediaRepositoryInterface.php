<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Port;

use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Data\WorkItem;

interface MediaRepositoryInterface
{
    public function ensureAsset(string $sourcePath): Asset;
    public function getAsset(int $assetId): Asset;
    public function recordStream(array $items): array;
    public function updateMetadata(int $assetId, array $media): void;
    public function activate(int $assetId, string $hash, string $cachePath, int $size): void;
    public function replaceGallery(int $productId, array $paths): void;
    public function replaceFileUsages(int $productId, array $references): void;
    public function galleryUsages(int $productId): array;
    public function fileUsages(int $productId): array;
    public function saveGalleryPath(int $productId, int $assetId, string $path): void;
    public function saveFilePath(int $productId, string $code, int $storeId, string $path): void;

    /**
     * Remove gallery usage records that are no longer desired.
     *
     * @param int $productId
     * @return void
     */
    public function purgeObsoleteGalleryUsages(int $productId): void;

    /**
     * Remove file attribute usage records that are no longer desired.
     *
     * @param int $productId
     * @return void
     */
    public function purgeObsoleteFileUsages(int $productId): void;

    public function materialization(int $assetId, string $scope): ?array;
    public function saveMaterialization(Asset $asset, string $scope, ?int $productId, string $path): void;
    public function scheduleProducts(array $productIds): void;
    public function claim(int $limit, int $leaseSeconds): array;
    public function complete(WorkItem $item): void;
    public function release(WorkItem $item, string $error, int $maxAttempts, int $delay): void;
    public function hasWork(): bool;
}
