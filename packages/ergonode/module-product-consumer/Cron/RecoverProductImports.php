<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;

use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\Queue\ProductImportRecoveryDispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

class RecoverProductImports
{
    public function __construct(
        private readonly AutomaticSynchronizationInterface $automation,
        private readonly ProductImportRecoveryDispatcher $recoveryDispatcher,
        private readonly LoggerInterface $logger,
        private readonly ProductImportConfig $config
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->automation->isAllowed()) {
            return;
        }
        try {
            $this->recoveryDispatcher->dispatch();
        } catch (ConnectionConfigurationException) {
            return;
        } catch (Throwable $exception) {
            $this->logger->error('Unable to recover Ergonode product imports.', ['exception' => $exception]);
        }
    }
}
