<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizerInterface;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class SynchronizeTemplates
{
    public function __construct(
        private readonly AutomaticSynchronizationInterface $automation,
        private readonly ConfigProvider $ergonodeConfigProvider,
        private readonly TemplateConfigProvider $templateConfigProvider,
        private readonly TemplateSynchronizerInterface $templateSynchronizer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws Throwable
     * @throws LocalizedException
     */
    public function execute(): void
    {
        if (!$this->ergonodeConfigProvider->isEnabled() || !$this->templateConfigProvider->isCronEnabled()) {
            return;
        }
        if (!$this->automation->isAllowed()) {
            return;
        }

        try {
            $result = $this->templateSynchronizer->execute();
            $this->logger->info('Ergonode template synchronization cron completed.', $result);
        } catch (ConnectionConfigurationException) {
            return;
        } catch (Throwable $exception) {
            $this->logger->error('Ergonode template synchronization cron failed.', ['exception' => $exception]);
            throw $exception;
        }
    }
}
