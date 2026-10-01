<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use Ergonode\CategoryConsumerHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryConsumerHistory\Model\Persistence\HistoryPrunerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class HistoryCleanup
{
    public function __construct(
        private readonly HistoryConfig $config,
        private readonly HistoryPrunerInterface $pruner,
        private readonly DateTime $dateTime,
        private readonly CategorySynchronizationLock $lock
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isCleanupEnabled()) {
            return;
        }
        $days = $this->config->getRetentionDays();
        if ($days === null) {
            return;
        }
        $cutoff = $this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() - $days * 86400);
        $this->lock->execute(fn (): int => $this->pruner->deleteBefore($cutoff));
    }
}
