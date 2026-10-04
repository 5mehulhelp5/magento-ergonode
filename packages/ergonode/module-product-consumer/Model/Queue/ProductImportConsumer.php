<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Queue;

use Ergonode\ProductConsumer\Exception\NonRetryableImportException;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\Magento\ProductIndexInvalidator;
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ProductImportConsumer
{
    public function __construct(
        private readonly ProductImportWorkRepositoryInterface $workRepository,
        private readonly ProductImportProcessor $processor,
        private readonly ProductImportQueuePublisher $queuePublisher,
        private readonly ProductImportConfig $config,
        private readonly ProductIndexInvalidator $indexInvalidator,
        private readonly LoggerInterface $logger,
        private readonly ?\Ergonode\ProductConsumer\Model\Pipeline\ProductBatchPipeline $pipeline = null
    ) {
    }

    public function process(string $message): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        $items = $this->workRepository->claim(
            $this->config->getConsumerBatchSize(),
            $this->config->getLeaseSeconds()
        );
        if ($this->pipeline !== null) {
            $context = $this->pipeline->run(array_map(
                static fn($item) => new \Ergonode\ProductConsumer\Model\Pipeline\BatchEntry($item), $items
            ));
            foreach ($context->entries as $entry) {
                try {
                    if ($entry->error === null) {
                        $this->workRepository->complete($entry->request);
                    } else {
                        $this->workRepository->release($entry->request, $entry->error->getMessage(),
                            max(1, $entry->request->attemptCount), 0);
                    }
                } catch (Throwable $storageError) {
                    $this->logger->error('Unable to record Ergonode product synchronization outcome.', [
                        'product_id' => $entry->productId, 'ergonode_sku' => $entry->sku(),
                        'stage' => 'postprocess:work-status', 'exception' => $storageError,
                    ]);
                }
            }
            if ($this->workRepository->hasClaimableWork()) { $this->queuePublisher->dispatch(); }
            return;
        }
        $changed = false;
        foreach ($items as $item) {
            try {
                $changed = $this->processor->process($item) || $changed;
                $this->workRepository->complete($item);
            } catch (NonRetryableImportException $exception) {
                $this->logger->error('Unable to import Ergonode product: correct media configuration before a new import.', [
                    'sku' => $item->sku, 'exception' => $exception,
                ]);
                $this->workRepository->release($item, $exception->getMessage(), max(1, $item->attemptCount), 0);
            } catch (Throwable $exception) {
                $this->workRepository->release($item, $exception->getMessage(), max(1, $item->attemptCount), 0);
                $this->logger->error('Unable to import Ergonode product.', [
                    'sku' => $item->sku, 'attempt' => $item->attemptCount, 'exception' => $exception,
                ]);
            }
        }
        if ($changed) {
            $this->indexInvalidator->invalidate();
        }
        if ($this->workRepository->hasClaimableWork()) {
            $this->queuePublisher->dispatch();
        }
    }
}
