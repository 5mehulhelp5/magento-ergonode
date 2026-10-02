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
        private readonly MaterializationCache $cache
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
        $this->cache->run(function () use ($items): void {
            foreach ($items as $item) {
                try {
                    $this->processor->process($item);
                    $this->repository->complete($item);
                } catch (Throwable $e) {
                    $retryDelay = min(900, 5 * (2 ** min(8, $item->attemptCount - 1)));
                    $this->repository->release(
                        $item,
                        $e->getMessage(),
                        $this->config->getMaximumAttempts(),
                        $retryDelay
                    );
                    $this->logger->error(
                        'Unable to synchronize Ergonode media.',
                        ['product_id' => $item->productId, 'exception' => $e]
                    );
                }
            }
        });
        if ($this->repository->hasWork()) {
            $this->publisher->dispatch();
        }
    }
}
