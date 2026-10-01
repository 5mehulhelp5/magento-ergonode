<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Queue;

use Ergonode\ProductConsumer\Exception\DependencyUnavailableException;
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
        private readonly LoggerInterface $logger
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
        $changed = false;
        foreach ($items as $item) {
            try {
                $changed = $this->processor->process($item) || $changed;
                $this->workRepository->complete($item);
            } catch (DependencyUnavailableException $exception) {
                $this->workRepository->release(
                    $item,
                    $exception->getMessage(),
                    $this->config->getMaximumAttempts(),
                    60,
                    true
                );
            } catch (Throwable $exception) {
                $delay = min(900, 5 * (2 ** min(8, max(0, $item->attemptCount - 1))));
                $this->workRepository->release(
                    $item,
                    $exception->getMessage(),
                    $this->config->getMaximumAttempts(),
                    $delay
                );
                $this->logger->error('Unable to import Ergonode product.', [
                    'sku' => $item->sku,
                    'attempt' => $item->attemptCount,
                    'exception' => $exception,
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
