<?php

declare(strict_types=1);

namespace Ergonode\Media\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;
use Ergonode\Core\Api\Exception\ConnectionConfigurationException;

use Ergonode\Media\Model\Import\StreamScheduler;
use Psr\Log\LoggerInterface;
use Throwable;

class ScheduleStream
{
    public function __construct(
        private readonly AutomaticSynchronizationInterface $automation,
        private readonly StreamScheduler $scheduler,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->automation->isAllowed()) {
            return;
        }
        try {
            $this->scheduler->schedule();
        } catch (ConnectionConfigurationException) {
            return;
        } catch (Throwable $e) {
            $this->logger->error('Unable to schedule Ergonode multimedia stream.', ['exception' => $e]);
        }
    }
}
