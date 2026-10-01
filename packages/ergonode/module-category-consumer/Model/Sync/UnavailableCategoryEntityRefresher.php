<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryEntityRefresherInterface;
use Magento\Framework\Exception\LocalizedException;

class UnavailableCategoryEntityRefresher implements CategoryEntityRefresherInterface
{
    public function refresh(int $magentoCategoryId): array
    {
        throw new LocalizedException(__('Enable Ergonode category attribute consumption first.'));
    }
}
