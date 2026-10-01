<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;

interface AttributeOptionSynchronizerInterface
{
    /**
     * @param string $attributeCode
     * @param AttributeOptionStateInterface $desiredOption
     * @return SynchronizationResultInterface
     */
    public function synchronize(
        string $attributeCode,
        AttributeOptionStateInterface $desiredOption
    ): SynchronizationResultInterface;
}
