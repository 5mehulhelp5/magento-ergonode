<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Queue;

use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;

class ProductImportRecoveryDispatcher
{
    public function __construct(
        private readonly ProductImportWorkRepositoryInterface $workRepository,
        private readonly ProductImportQueuePublisher $queuePublisher,
        private readonly ProductImportConfig $config
    ) {
    }

    public function dispatch(): void
    {
        if ($this->config->isEnabled() && $this->workRepository->hasClaimableWork()) {
            $this->queuePublisher->dispatch();
        }
    }
}
