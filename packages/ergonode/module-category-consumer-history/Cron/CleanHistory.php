<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Cron;

use Ergonode\CategoryConsumerHistory\Model\HistoryCleanup;

class CleanHistory
{
    public function __construct(private readonly HistoryCleanup $cleanup)
    {
    }

    public function execute(): void
    {
        $this->cleanup->execute();
    }
}
