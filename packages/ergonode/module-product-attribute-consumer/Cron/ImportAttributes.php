<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\ProductAttributeConsumer\Model\Config\ProductAttributeConfigProvider;
use Ergonode\ProductAttributeConsumer\Model\Import\AttributeImportProcess;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class ImportAttributes
{
    public function __construct(
        private readonly AutomaticSynchronizationInterface $automation,
        private readonly ConfigProvider $configProvider,
        private readonly ProductAttributeConfigProvider $attributeConfigProvider,
        private readonly AttributeImportProcess $attributeImportProcess,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws Throwable
     * @throws LocalizedException
     */
    public function execute(): void
    {
        if (!$this->configProvider->isEnabled() || !$this->attributeConfigProvider->isImportCronEnabled()) {
            return;
        }
        if (!$this->automation->isAllowed()) {
            return;
        }

        try {
            $result = $this->attributeImportProcess->executeBatch();
            $this->logger->info('Ergonode attribute and option synchronization cron batch completed.', [
                'result' => $result,
            ]);
        } catch (ConnectionConfigurationException) {
            return;
        } catch (Throwable $exception) {
            $this->logger->error('Ergonode attribute and option synchronization cron batch failed.', [
                'exception' => $exception,
            ]);
            throw $exception;
        }
    }
}
