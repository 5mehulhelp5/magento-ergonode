<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;

interface OptionDefinitionPublisherInterface
{
    /**
     * Build the definition to publish from Magento source metadata.
     *
     * @param string $magentoAttributeCode
     * @param string $ergonodeAttributeCode
     * @param array<string, mixed> $source
     * @return AttributeOptionStateInterface
     */
    public function prepareState(
        string $magentoAttributeCode,
        string $ergonodeAttributeCode,
        array $source
    ): AttributeOptionStateInterface;

    /**
     * @param  string                        $attributeCode
     * @param  AttributeOptionStateInterface $state
     * @return SynchronizationResultInterface
     */
    public function publish(
        string $attributeCode,
        AttributeOptionStateInterface $state
    ): SynchronizationResultInterface;

    /**
     * @param  string                          $attributeCode
     * @param  AttributeOptionStateInterface[] $states
     * @return array<string, AttributeOptionSynchronizationResultInterface>
     */
    public function publishBatch(string $attributeCode, array $states): array;
}
