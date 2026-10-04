<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ResourceModel;

use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Data\WorkItem;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Throwable;

class MediaRepository implements MediaRepositoryInterface
{
    private const string ASSET = 'ergonode_media_asset';
    private const string MATERIALIZATION = 'ergonode_media_materialization';
    private const string GALLERY = 'ergonode_media_product_usage';
    private const string WORK = 'ergonode_media_product_work';
    private const string FILE = 'ergonode_media_file_usage';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly DateTime $dateTime,
        private readonly File $file,
        private readonly LoggerInterface $logger
    ) {
    }

    public function ensureAsset(string $sourcePath): Asset
    {
        $sourcePath = $this->sourcePath($sourcePath);
        $hash = hash('sha256', $sourcePath, true);
        $this->connection()->insertOnDuplicate($this->table(self::ASSET), [[
            'source_path_hash' => $hash,
            'source_path' => $sourcePath,
        ]], []);
        $row = $this->connection()->fetchRow($this->connection()->select()
            ->from($this->table(self::ASSET))->where('source_path_hash = ?', $hash)->limit(1));
        if (!is_array($row) || (string)$row['source_path'] !== $sourcePath) {
            throw new LocalizedException(__('Ergonode media source path hash collision detected.'));
        }
        return $this->asset($row);
    }

    public function getAsset(int $assetId): Asset
    {
        $row = $this->connection()->fetchRow($this->connection()->select()
            ->from($this->table(self::ASSET))->where('asset_id = ?', $assetId)->limit(1));
        if (!is_array($row)) {
            throw new LocalizedException(__('Ergonode media asset %1 does not exist.', $assetId));
        }
        return $this->asset($row);
    }

    /**
     * @param list<array{
     *     cursor: string,
     *     path: string,
     *     url: string,
     *     name: string,
     *     extension: string,
     *     mime: string,
     *     size: int
     * }> $items
     * @return int[]
     */
    public function recordStream(array $items): array
    {
        $products = [];
        $galleryProducts = [];
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            foreach ($items as $item) {
                $asset = $this->ensureAsset($item['path']);
                $cursor = trim($item['cursor']);
                $last = $connection->fetchOne($connection->select()->from(
                    $this->table(self::ASSET),
                    ['last_cursor']
                )->where('asset_id = ?', $asset->id)->forUpdate(true));
                if ($cursor === '' || ($last !== false && hash_equals((string)$last, $cursor))) {
                    continue;
                }
                $connection->update($this->table(self::ASSET), [
                    'download_url' => trim($item['url']),
                    'name' => mb_substr(trim($item['name']), 0, 128),
                    'extension' => $this->extension($item['extension'], $item['path']),
                    'mime_type' => mb_substr(trim($item['mime']), 0, 128),
                    'size' => max(0, $item['size']),
                    'revision' => new Expression('revision + 1'),
                    'last_cursor' => $cursor,
                    'status' => 'dirty',
                ], ['asset_id = ?' => $asset->id]);
                foreach ($connection->fetchCol($connection->select()->from(
                    $this->table(self::GALLERY),
                    ['product_id']
                )->where('asset_id = ?', $asset->id)->where('desired = ?', 1)) as $id) {
                    $products[(int)$id] = (int)$id;
                    $galleryProducts[(int)$id] = (int)$id;
                }
                foreach ($connection->fetchCol($connection->select()->from(
                    $this->table(self::FILE),
                    ['product_id']
                )->where('source_path_hash = ?', hash('sha256', $asset->sourcePath, true))
                    ->where($connection->quoteInto('BINARY source_path = BINARY ?', $asset->sourcePath))
                    ->where('desired = ?', 1)) as $id) {
                    $products[(int)$id] = (int)$id;
                }
            }
            $this->scheduleProducts(array_values($galleryProducts), true);
            $this->scheduleProducts(array_values(array_diff($products, $galleryProducts)));
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        return array_values($products);
    }

    public function updateMetadata(int $assetId, array $media, int $expectedRevision): void
    {
        $this->connection()->update($this->table(self::ASSET), [
            'download_url' => trim((string)$media['url']),
            'name' => mb_substr(trim((string)$media['name']), 0, 128),
            'extension' => $this->extension((string)$media['extension'], (string)$media['path']),
            'mime_type' => mb_substr(trim((string)$media['mime']), 0, 128),
            'size' => max(0, (int)$media['size']),
        ], ['asset_id = ?' => $assetId, 'revision = ?' => $expectedRevision]);
        if ($this->getAsset($assetId)->revision !== $expectedRevision) {
            throw new LocalizedException(__('Ergonode media changed while its metadata was being fetched.'));
        }
    }

    public function activate(int $assetId, string $hash, string $cachePath, int $size, int $expectedRevision): void
    {
        $affected = $this->connection()->update($this->table(self::ASSET), [
            'content_hash' => $hash, 'cache_path' => $cachePath, 'size' => $size, 'status' => 'active',
        ], ['asset_id = ?' => $assetId, 'revision = ?' => $expectedRevision]);
        if ($affected === 0 && $this->getAsset($assetId)->revision !== $expectedRevision) {
            throw new LocalizedException(__('Ergonode media changed while its file was being downloaded.'));
        }
    }

    /** @param string[] $paths */
    public function replaceGallery(int $productId, array $paths): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $connection->update($this->table(self::GALLERY), ['desired' => 0], ['product_id = ?' => $productId]);
            $position = 0;
            foreach (array_values(array_unique(array_filter(array_map('trim', $paths)))) as $path) {
                $asset = $this->ensureAsset($path);
                $connection->insertOnDuplicate($this->table(self::GALLERY), [[
                    'product_id' => $productId, 'asset_id' => $asset->id,
                    'position' => $position++, 'desired' => 1,
                ]], ['position', 'desired']);
            }
            $this->scheduleProducts([$productId], true);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @param list<array{source_path: string,attribute_code: string,store_id: int}> $references */
    public function replaceFileUsages(
        int $productId,
        array $references,
        ?array $attributeCodes = null,
        array $preservedAttributeCodes = []
    ): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $where = ['product_id = ?' => $productId];
            if ($attributeCodes !== null) {
                // An empty scope deliberately updates no attributes.
                $where['attribute_code IN (?)'] = $attributeCodes === [] ? [''] : $attributeCodes;
            }
            if ($preservedAttributeCodes !== []) {
                $where['attribute_code NOT IN (?)'] = $preservedAttributeCodes;
            }
            $connection->update($this->table(self::FILE), ['desired' => 0], $where);
            foreach ($references as $reference) {
                $path = $this->sourcePath($reference['source_path']);
                $connection->insertOnDuplicate($this->table(self::FILE), [[
                    'product_id' => $productId,
                    'attribute_code' => trim($reference['attribute_code']),
                    'store_id' => max(0, $reference['store_id']),
                    'source_path_hash' => hash('sha256', $path, true),
                    'source_path' => $path,
                    'attached_path' => null,
                    'desired' => 1,
                ]], ['source_path_hash', 'source_path', 'desired']);
            }
            $this->scheduleProducts([$productId]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @return list<array{asset: Asset,position: int,desired: bool,attached_path: ?string}> */
    public function galleryUsages(int $productId): array
    {
        $rows = $this->connection()->fetchAll($this->connection()->select()->from(
            ['usage' => $this->table(self::GALLERY)],
            ['asset_id', 'position', 'desired', 'attached_path']
        )->where('product_id = ?', $productId)->order(['position ASC', 'asset_id ASC']));
        return array_map(fn (array $row): array => [
            'asset' => $this->getAsset((int)$row['asset_id']), 'position' => (int)$row['position'],
            'desired' => (bool)$row['desired'],
            'attached_path' => $row['attached_path'] === null ? null : (string)$row['attached_path'],
        ], $rows);
    }

    /** @return list<array{source_path: string,attribute_code: string,store_id: int,attached_path: ?string,desired: bool}> */
    public function fileUsages(int $productId): array
    {
        return array_map(static fn (array $row): array => [
            'source_path' => (string)$row['source_path'], 'attribute_code' => (string)$row['attribute_code'],
            'store_id' => (int)$row['store_id'],
            'attached_path' => $row['attached_path'] === null ? null : (string)$row['attached_path'],
            'desired' => (bool)$row['desired'],
        ], $this->connection()->fetchAll($this->connection()->select()->from($this->table(self::FILE))
            ->where('product_id = ?', $productId)->order(['attribute_code ASC', 'store_id ASC'])));
    }

    public function saveGalleryPath(int $productId, int $assetId, string $path): void
    {
        $this->connection()->update($this->table(self::GALLERY), ['attached_path' => $path], [
            'product_id = ?' => $productId, 'asset_id = ?' => $assetId, 'desired = ?' => 1,
        ]);
    }

    public function saveFilePath(int $productId, string $code, int $storeId, string $path): void
    {
        $this->connection()->update($this->table(self::FILE), ['attached_path' => $path], [
            'product_id = ?' => $productId, 'attribute_code = ?' => $code,
            'store_id = ?' => $storeId, 'desired = ?' => 1,
        ]);
    }

    public function removeFileUsage(int $productId, string $code, int $storeId, string $sourcePath): void
    {
        $this->connection()->delete($this->table(self::FILE), [
            'product_id = ?' => $productId, 'attribute_code = ?' => $code, 'store_id = ?' => $storeId,
            // Do not delete a different source subsequently recorded for this role.
            'source_path_hash = ?' => hash('sha256', $this->sourcePath($sourcePath), true),
        ]);
    }

    public function purgeObsoleteGalleryUsages(int $productId): void
    {
        $this->connection()->delete($this->table(self::GALLERY), ['product_id = ?' => $productId, 'desired = ?' => 0]);
    }

    public function purgeObsoleteFileUsages(int $productId, array $preservedAttributeCodes = []): void
    {
        $where = ['product_id = ?' => $productId, 'desired = ?' => 0];
        if ($preservedAttributeCodes !== []) {
            $where['attribute_code NOT IN (?)'] = $preservedAttributeCodes;
        }
        $this->connection()->delete($this->table(self::FILE), $where);
    }

    /** @return array{path: string,content_hash: string,revision: int}|null */
    public function materialization(int $assetId, string $scope): ?array
    {
        $row = $this->connection()->fetchRow($this->connection()->select()->from($this->table(self::MATERIALIZATION))
            ->where('asset_id = ?', $assetId)->where('scope_key = ?', $scope)->limit(1));
        return !is_array($row) ? null : [
            'path' => (string)$row['local_path'], 'content_hash' => (string)$row['content_hash'],
            'revision' => (int)$row['revision'],
        ];
    }

    public function saveMaterialization(Asset $asset, string $scope, ?int $productId, string $path): void
    {
        $this->connection()->insertOnDuplicate($this->table(self::MATERIALIZATION), [[
            'asset_id' => $asset->id, 'scope_key' => $scope, 'product_id' => $productId,
            'local_path_hash' => hash('sha256', $path, true), 'local_path' => $path,
            'content_hash' => $asset->contentHash, 'revision' => $asset->revision,
        ]], ['product_id', 'local_path_hash', 'local_path', 'content_hash', 'revision']);
    }

    /** @param int[] $productIds */
    public function scheduleProducts(array $productIds, bool $synchronizeGallery = false): void
    {
        $rows = [];
        foreach (array_unique(array_map('intval', $productIds)) as $id) {
            if ($id > 0) {
                $rows[] = ['product_id' => $id, 'synchronize_gallery' => (int)$synchronizeGallery,
                    'status' => 'pending', 'attempt_count' => 0,
                    'available_at' => $this->now(), 'lease_token' => null, 'lease_expires_at' => null,
                    'last_error' => null];
            }
        }
        if ($rows !== []) {
            $this->expireWork(array_column($rows, 'product_id'));
            $columns = ['status', 'attempt_count', 'available_at', 'lease_token', 'lease_expires_at', 'last_error'];
            // File-only work must not cancel a gallery update already waiting for this product.
            if ($synchronizeGallery) {
                $columns[] = 'synchronize_gallery';
            }
            $this->connection()->insertOnDuplicate($this->table(self::WORK), $rows, $columns);
        }
    }

    /** @return WorkItem[] */
    public function claim(int $limit, int $leaseSeconds): array
    {
        return $this->claimMatching($limit, $leaseSeconds);
    }

    public function claimProduct(int $productId, int $leaseSeconds): ?WorkItem
    {
        return $this->claimMatching(1, $leaseSeconds, [$productId])[0] ?? null;
    }

    private function claimMatching(int $limit, int $leaseSeconds, ?array $productIds = null): array
    {
        $connection = $this->connection();
        $token = random_bytes(16);
        $connection->beginTransaction();
        try {
            $this->expireWork($productIds);
            $claimable = $this->pendingWorkCondition();
            $select = $connection->select()->from($this->table(self::WORK))->where($claimable);
            if ($productIds !== null) { $select->where('product_id IN (?)', $productIds); }
            $rows = $connection->fetchAll($select->order('product_id ASC')->limit(max(1, $limit))->forUpdate(true));
            $ids = array_map(static fn (array $row): int => (int)$row['product_id'], $rows);
            if ($ids !== []) {
                $connection->update($this->table(self::WORK), [
                    'status' => 'processing', 'attempt_count' => new Expression('attempt_count + 1'),
                    'lease_token' => $token,
                    'lease_expires_at' => $this->dateTime->gmtDate(null, time() + $leaseSeconds),
                    'last_error' => null,
                ], ['product_id IN (?)' => $ids]);
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        return array_map(fn (array $row): WorkItem => new WorkItem(
            (int)$row['product_id'],
            $token,
            (int)$row['attempt_count'] + 1,
            isset($row['synchronize_gallery'])
                ? (bool)$row['synchronize_gallery']
                : (bool)$connection->fetchOne($connection->select()->from($this->table(self::GALLERY), ['product_id'])
                    ->where('product_id = ?', (int)$row['product_id'])->limit(1))
        ), $rows);
    }

    public function complete(WorkItem $item): bool
    {
        return $this->connection()->delete($this->table(self::WORK), [
            'product_id = ?' => $item->productId, 'lease_token = ?' => $item->leaseToken,
        ]) === 1;
    }

    public function applyWork(WorkItem $item, callable $write): bool
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            // A reschedule replaces the token. Lock until all product and usage writes finish,
            // so another claim or reschedule cannot invalidate this check during the write.
            $row = $connection->fetchRow($connection->select()->from($this->table(self::WORK))
                ->where('product_id = ?', $item->productId)->forUpdate(true));
            if (!is_array($row) || $row['status'] !== 'processing'
                || !hash_equals((string)$row['lease_token'], $item->leaseToken)
            ) {
                $connection->commit();
                return false;
            }
            if ((string)$row['lease_expires_at'] <= $this->now()) {
                // Expired work fails without writing product data or resubmitting the task.
                throw new LocalizedException(__('Ergonode media work lease expired before the product write.'));
            }
            $write();
            $connection->commit();
            return true;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function fail(WorkItem $item, string $error): void
    {
        $this->connection()->update($this->table(self::WORK), [
            'status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null,
            'last_error' => mb_substr($error, 0, 65535),
        ], ['product_id = ?' => $item->productId, 'lease_token = ?' => $item->leaseToken]);
    }

    public function hasWork(): bool
    {
        return $this->connection()->fetchOne($this->connection()->select()->from(
            $this->table(self::WORK), [new Expression('1')]
        )->where($this->pendingWorkCondition())->limit(1)) !== false;
    }

    public function hasFailedWork(int $productId): bool
    {
        $connection = $this->connection();
        return $connection->fetchOne($connection->select()->from($this->table(self::WORK), ['product_id'])
            ->where('product_id = ?', $productId)
            ->where('(' . $connection->quoteInto('status = ?', 'failed') . ') OR ('
                . $this->interruptedWorkCondition() . ')')->limit(1)) !== false;
    }

    private function pendingWorkCondition(): string
    {
        $connection = $this->connection();
        return $connection->quoteInto('status = ?', 'pending') . ' AND attempt_count = 0 AND '
            . $connection->quoteInto('available_at <= ?', $this->now());
    }

    private function interruptedWorkCondition(): string
    {
        $connection = $this->connection();
        return '(' . $connection->quoteInto('status = ?', 'processing') . ' AND '
            . $connection->quoteInto('lease_expires_at <= ?', $this->now()) . ') OR ('
            . $connection->quoteInto('status = ?', 'pending') . ' AND attempt_count > 0)';
    }

    /** Retire interrupted/legacy retry work; a fresh import may then schedule current data. */
    private function expireWork(?array $productIds = null): void
    {
        $connection = $this->connection();
        $select = $connection->select()->from($this->table(self::WORK))->where($this->interruptedWorkCondition());
        if ($productIds !== null) {
            $select->where('product_id IN (?)', $productIds);
        }
        foreach ($connection->fetchAll($select) as $row) {
            $where = ['product_id = ?' => (int)$row['product_id'], 'status = ?' => $row['status']];
            if ($row['status'] === 'processing') {
                $where['lease_token = ?'] = $row['lease_token'];
                $where['lease_expires_at <= ?'] = $this->now();
                $error = 'Media execution was interrupted or its lease expired. Start a new import to process this product.';
            } else {
                $where['attempt_count > ?'] = 0;
                $error = (string)($row['last_error'] ?? 'Previous media attempt did not complete.');
            }
            if ($connection->update($this->table(self::WORK), [
                'status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null, 'last_error' => $error,
            ], $where) > 0) {
                $this->logger->error('Unfinished Ergonode media work requires a new import.', [
                    'product_id' => (int)$row['product_id'], 'reason' => $error,
                ]);
            }
        }
    }

    private function asset(array $row): Asset
    {
        return new Asset(
            (int)$row['asset_id'],
            (string)$row['source_path'],
            $row['download_url'] === null ? null : (string)$row['download_url'],
            (string)$row['name'],
            (string)$row['extension'],
            (string)$row['mime_type'],
            $row['content_hash'] === null ? null : (string)$row['content_hash'],
            $row['cache_path'] === null ? null : (string)$row['cache_path'],
            (int)$row['revision'],
            (string)$row['status']
        );
    }

    private function sourcePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || in_array('..', explode('/', $path), true) || mb_strlen($path) > 2048) {
            throw new LocalizedException(__('Invalid Ergonode multimedia path "%1".', $path));
        }
        return $path;
    }

    private function extension(string $extension, string $fallback): string
    {
        $extension = strtolower(trim($extension, " .\t\n\r\0\x0B"));
        if ($extension === '') {
            $extension = strtolower((string)($this->file->getPathInfo($fallback)['extension'] ?? ''));
        }
        $blocked = ['asp', 'aspx', 'cgi', 'html', 'jsp', 'phtml', 'phar', 'php', 'pl', 'py', 'sh'];
        return preg_match('/^[a-z0-9]{1,16}$/', $extension) === 1 && !in_array($extension, $blocked, true)
            ? $extension : 'bin';
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
    private function now(): string
    {
        return (string)$this->dateTime->gmtDate();
    }
}
