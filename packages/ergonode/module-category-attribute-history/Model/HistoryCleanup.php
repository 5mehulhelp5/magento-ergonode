<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model;

use Ergonode\CategoryAttributeHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryAttributeHistory\Model\Persistence\HistoryPrunerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class HistoryCleanup
{
    public function __construct(
        private readonly HistoryConfig $config,
        private readonly HistoryPrunerInterface $pruner,
        private readonly DateTime $dateTime
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
        $this->pruner->deleteBefore($cutoff);
    }
}
