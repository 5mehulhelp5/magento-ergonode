<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Magento\Framework\Exception\LocalizedException;

interface AttributeCreateBatchSynchronizerInterface
{
    /**
     * Create all supplied attributes in mutation batches and verify rejected existing codes.
     *
     * @param AttributeStateInterface[] $desiredStates
     * @return array<string, AttributeSynchronizationResultInterface>
     * @throws LocalizedException
     */
    public function synchronizeBatch(array $desiredStates): array;
}
