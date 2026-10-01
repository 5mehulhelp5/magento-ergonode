<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;

interface AttributeDefinitionPublisherInterface
{
    /**
     * Build the definition to publish from Magento source metadata.
     *
     * @param  array<string, mixed> $source
     * @return AttributeStateInterface
     */
    public function prepareState(array $source): AttributeStateInterface;

    /**
     * @param  AttributeStateInterface $state
     * @return AttributeSynchronizationResultInterface
     */
    public function publish(AttributeStateInterface $state): AttributeSynchronizationResultInterface;

    /**
     * @param  AttributeStateInterface[] $states
     * @return array<string, AttributeSynchronizationResultInterface>
     */
    public function publishBatch(array $states): array;
}
