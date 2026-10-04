<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Materialization;

use Ergonode\Media\Model\Index\LocalFiles;
use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Ergonode\Media\Model\ResourceModel\OrphanImageReferences;
use Ergonode\ProductMedia\Api\OrphanImageCleanerInterface;
use Ergonode\ProductMedia\Model\Gallery\GalleryWriteLocks;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Model\CallbackPool;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class OrphanImageCleaner implements OrphanImageCleanerInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly OrphanImageReferences $references,
        private readonly LocalFiles $files,
        private readonly File $driver,
        private readonly LocalFileIndexInterface $index,
        private readonly GalleryWriteLocks $locks,
        private readonly LoggerInterface $logger
    ) {
    }

    public function schedule(int $productId, array $paths): void
    {
        if ($paths === []) { return; }
        $cleanup = function () use ($productId, $paths): void {
            foreach (array_unique($paths) as $path) {
                $stage = 'reference check';
                try {
                    $this->locks->forPath($path, function () use ($path, &$stage): void {
                        if ($this->references->isUsed($path)) { return; }
                        $stage = 'file deletion';
                        if ($this->files->stat($path) !== null && !$this->driver->deleteFile($this->files->absolute($path))) {
                            throw new RuntimeException('The filesystem rejected deletion of the unused image.');
                        }
                        $stage = 'mapping cleanup';
                        $this->references->forgetUnusedFile($path);
                        $this->index->remove($path);
                    });
                } catch (Throwable $exception) {
                    // The product transaction has committed; cleanup failure must not resubmit its writes.
                    $this->logger->error('Unable to remove unused product media.', [
                        'product_id' => $productId, 'path' => $path, 'stage' => $stage, 'exception' => $exception,
                    ]);
                }
            }
        };
        $connection = $this->resource->getConnection();
        if ($connection->getTransactionLevel() > 0) {
            CallbackPool::attach(spl_object_hash($connection), $cleanup);
        } else {
            $cleanup();
        }
    }
}
