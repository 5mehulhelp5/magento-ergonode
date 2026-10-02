<?php

declare(strict_types=1);

namespace Ergonode\Media\Cron;

use Ergonode\Core\Api\AutomaticSynchronizationInterface;

use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Media\Model\Index\ScanReadiness;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\Media\Model\Queue\QueuePublisher;

class RecoverWork
{
    public function __construct(
        private readonly AutomaticSynchronizationInterface $automation,
        private readonly MediaRepositoryInterface $repository,
        private readonly QueuePublisher $publisher,
        private readonly ConfigProvider $config,
        private readonly ScanReadiness $scanReadiness
    ) {
    }

    public function execute(): void
    {
        if ($this->config->isEnabled()
            && !$this->scanReadiness->isBlocked()
            && $this->repository->hasWork()
            && $this->automation->isAllowed()
        ) {
            $this->publisher->dispatch();
        }
    }
}
