<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Pipeline;

use Ergonode\ProductConsumer\Api\BatchProcessorInterface;
use Ergonode\ProductConsumer\Model\Pipeline\BatchContext;
use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\Gallery\WorkProcessor;
use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Materialization\MaterializationCache;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class WriteProductMedia implements BatchProcessorInterface
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly WorkProcessor $writer,
        private readonly MediaConfig $config,
        private readonly ScanReadiness $scan,
        private readonly MaterializationCache $cache
    ) {
    }

    public function process(BatchContext $context): void
    {
        $this->cache->run(function () use ($context): void {
            foreach ($context->entries as $entry) {
                $id = $entry->productId;
                if ($id === null || !isset($context->media[$id])) { continue; }
                $context->run($entry, 'process:media', function () use ($context, $id): void {
                    if ($this->scan->isBlocked()) {
                        throw new LocalizedException(__('Complete the initial media index scan before importing product media.'));
                    }
                    ($context->media[$id])();
                    $work = $this->repository->claimProduct($id, $this->config->getLeaseSeconds());
                    if ($work === null) {
                        throw new LocalizedException(__('Media work for product %1 is not available to this import.', $id));
                    }
                    try {
                        $this->writer->process($work);
                        if (!$this->repository->complete($work)) {
                            throw new LocalizedException(__('Media work for product %1 changed before completion.', $id));
                        }
                    } catch (Throwable $error) {
                        try {
                            $this->repository->fail($work, $error->getMessage());
                        } catch (Throwable $storageError) {
                            throw new LocalizedException(__(
                                'Media failed: %1. Recording the failure also failed: %2.',
                                $error->getMessage(), $storageError->getMessage()
                            ), $error);
                        }
                        throw $error;
                    }
                });
            }
        });
    }
}
