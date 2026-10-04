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

    /** Legacy scheduled rows may still call this handler; never revive failed or interrupted work. */
    public function execute(): void
    {
    }
}
