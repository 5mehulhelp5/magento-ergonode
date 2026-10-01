<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Cron;

use Ergonode\CategoryAttributeHistory\Model\HistoryCleanup;

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
