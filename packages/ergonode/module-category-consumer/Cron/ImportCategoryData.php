<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Cron;

use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;

use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Psr\Log\LoggerInterface;
use Throwable;

class ImportCategoryData
{
    public function __construct(
        private readonly AutomaticSynchronizationInterface $automation,
        private readonly CategoryStreamEligibility $eligibility,
        private readonly CategoryDataSynchronizationProcessInterface $synchronizationProcess,
        private readonly CategoryReconciliationErrorFormatter $errorFormatter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->eligibility->canRunCron(data: true)) {
            return;
        }
        if (!$this->automation->isAllowed()) {
            return;
        }
        try {
            $stats = $this->synchronizationProcess->execute();
            $this->logger->info('Ergonode category data stream processed.', $stats);
        } catch (ConnectionConfigurationException) {
            return;
        } catch (GraphQlRequestException $exception) {
            $this->logger->error(
                'Ergonode category data stream request failed.',
                $this->errorFormatter->format($exception)
            );
        } catch (Throwable $exception) {
            $this->logger->error('Ergonode category data stream processing failed.', [
                'exception' => $exception,
            ]);
        }
    }
}
