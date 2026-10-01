<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\ProductPublisher\Api\Data\ProductStateInterface;
use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Magento\Framework\Exception\LocalizedException;

interface ProductBatchSynchronizerInterface
{
    /**
     * @param ProductStateInterface[] $desiredStates
     * @param string $mode
     * @return ProductSynchronizationResultInterface[]
     * @throws LocalizedException
     */
    public function synchronizeBatch(
        array $desiredStates,
        string $mode = ProductSynchronizerInterface::MODE_UPDATE
    ): array;
}
