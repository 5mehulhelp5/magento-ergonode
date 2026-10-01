<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Api;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationResultInterface;
use Magento\Framework\Exception\LocalizedException;

interface CategoryBatchSynchronizerInterface
{
    /**
     * @param CategoryStateInterface[] $desiredStates
     * @param string $mode
     * @return array<string, CategorySynchronizationResultInterface>
     * @throws LocalizedException
     */
    public function synchronizeBatch(
        array $desiredStates,
        string $mode = CategorySynchronizerInterface::MODE_UPDATE
    ): array;
}
