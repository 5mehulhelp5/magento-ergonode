<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Cron;

use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;

use Ergonode\CategoryConsumer\Model\Sync\CategoryStreamEligibility;
use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Psr\Log\LoggerInterface;
use Throwable;

class ImportCategories
{
    public function __construct(
        private readonly AutomaticSynchronizationInterface $automation,
        private readonly CategoryStreamEligibility $eligibility,
        private readonly CategoryStructureSynchronizationProcessInterface $synchronizationProcess,
        private readonly CategoryReconciliationErrorFormatter $errorFormatter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->eligibility->canRunCron()) {
            return;
        }
        if (!$this->automation->isAllowed()) {
            return;
        }
        try {
            $stats = $this->synchronizationProcess->execute();
            if ($stats['conflicts'] > 0) {
                $this->logger->warning('Ergonode category structure stream completed with conflicts.', $stats);
            } else {
                $this->logger->info('Ergonode category structure stream processed.', $stats);
            }
        } catch (ConnectionConfigurationException) {
            return;
        } catch (GraphQlRequestException $exception) {
            $this->logger->error(
                'Ergonode category structure stream request failed.',
                $this->errorFormatter->format($exception)
            );
        } catch (Throwable $exception) {
            $this->logger->error('Ergonode category structure stream processing failed.', [
                'exception' => $exception,
            ]);
        }
    }
}
