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
    public function updateMetadata(int $assetId, array $media, int $expectedRevision): void;
    public function activate(int $assetId, string $hash, string $cachePath, int $size, int $expectedRevision): void;
    public function replaceGallery(int $productId, array $paths): void;
    public function replaceFileUsages(int $productId, array $references, ?array $attributeCodes = null, array $preservedAttributeCodes = []): void;
    public function galleryUsages(int $productId): array;
    public function fileUsages(int $productId): array;
    public function saveGalleryPath(int $productId, int $assetId, string $path): void;
    /** Remove an obsolete role reference only after its gallery and role writes succeed. */
    public function removeFileUsage(int $productId, string $code, int $storeId, string $sourcePath): void;
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
    public function purgeObsoleteFileUsages(int $productId, array $preservedAttributeCodes = []): void;

    public function materialization(int $assetId, string $scope): ?array;
    public function saveMaterialization(Asset $asset, string $scope, ?int $productId, string $path): void;
    public function scheduleProducts(array $productIds, bool $synchronizeGallery = false): void;
    public function claim(int $limit, int $leaseSeconds): array;
    /** Claim only this batch product, never drain unrelated queued media. */
    public function claimProduct(int $productId, int $leaseSeconds): ?WorkItem;
    public function complete(WorkItem $item): bool;
    /** Apply product writes atomically only while this worker still owns the current task. */
    public function applyWork(WorkItem $item, callable $write): bool;
    public function fail(WorkItem $item, string $error): void;
    /** Failed or interrupted media from an earlier pass; never claimed automatically. */
    public function hasFailedWork(int $productId): bool;
    public function hasWork(): bool;
}
