<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Magento\Framework\Exception\LocalizedException;

class CategorySynchronizationPaused extends LocalizedException
{
    public function __construct()
    {
        parent::__construct(__('Synchronization paused. Completed changes are kept.'));
    }
}
