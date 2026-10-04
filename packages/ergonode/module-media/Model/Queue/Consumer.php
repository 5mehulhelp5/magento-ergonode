<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Queue;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Config\MediaConfig;
use Ergonode\Media\Model\Gallery\WorkProcessor;
use Ergonode\Media\Model\Materialization\MaterializationCache;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Consumer
{
    public function __construct(
        private readonly MediaRepositoryInterface $repository,
        private readonly WorkProcessor $processor,
        private readonly QueuePublisher $publisher,
        private readonly MediaConfig $config,
        private readonly ConfigProvider $connection,
        private readonly LoggerInterface $logger,
        private readonly ScanReadiness $scanReadiness,
        private readonly MaterializationCache $cache,
        private readonly ?\Ergonode\Product\Model\Cache\ProductCacheFinalizer $productCache = null
    ) {
    }

    public function process(string $message): void
    {
        if (!$this->connection->isEnabled() || $this->scanReadiness->isBlocked()) {
            return;
        }
        $items = $this->repository->claim(
            $this->config->getConsumerBatchSize(),
            $this->config->getLeaseSeconds()
        );
        try {
            $before = $this->productCache?->begin(array_map(static fn($item): int => $item->productId, $items)) ?? [];
        } catch (Throwable $error) {
            $this->logger->error('Unable to prepare product cache state before Ergonode media synchronization.', [
                'product_ids' => array_map(static fn($item): int => $item->productId, $items),
                'stage' => 'preprocess:cache', 'exception' => $error,
            ]);
            foreach ($items as $item) {
                try {
                    $this->repository->fail($item, $error->getMessage());
                } catch (Throwable $storageError) {
                    $this->logger->error('Unable to record failed Ergonode media work.', [
                        'product_id' => $item->productId, 'exception' => $storageError,
                    ]);
                }
            }
            if ($this->repository->hasWork()) {
                $this->publisher->dispatch();
            }
            return;
        }
        $completed = [];
        $this->cache->run(function () use ($items, &$completed): void {
            foreach ($items as $item) {
                try {
                    $this->processor->process($item);
                    $completedWork = $this->repository->complete($item);
                    if (!$completedWork && $this->productCache !== null) {
                        throw new \Magento\Framework\Exception\LocalizedException(__(
                            'Media work for product %1 changed before completion.', $item->productId
                        ));
                    }
                    if ($completedWork) { $completed[] = $item->productId; }
                } catch (Throwable $e) {
                    $this->logger->error(
                        'Unable to synchronize Ergonode media.',
                        ['product_id' => $item->productId, 'exception' => $e]
                    );
                    try {
                        $this->repository->fail($item, $e->getMessage());
                    } catch (Throwable $storageError) {
                        $this->logger->error('Unable to record failed Ergonode media work.', [
                            'product_id' => $item->productId, 'exception' => $storageError,
                        ]);
                    }
                }
            }
        });
        if ($this->productCache !== null) {
            try {
                $this->productCache->complete($this->productCache->changes($completed, $before));
            } catch (Throwable $error) {
                $this->logger->error('Unable to refresh product cache after Ergonode media synchronization.', [
                    'product_ids' => $completed, 'stage' => 'postprocess:cache', 'exception' => $error,
                ]);
            }
        }
        if ($this->repository->hasWork()) {
            $this->publisher->dispatch();
        }
    }
}
