<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;

interface AttributeOptionBatchSynchronizerInterface
{
    /**
     * @param string $attributeCode
     * @param AttributeOptionStateInterface[] $desiredOptions
     * @return array<string, AttributeOptionSynchronizationResultInterface>
     */
    public function synchronizeBatch(string $attributeCode, array $desiredOptions): array;
}
