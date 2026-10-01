<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributePublisher\Api;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;

interface AttributeSourceStateBuilderInterface
{
    /**
     * @param string $ergonodeAttributeCode
     * @param string $magentoAttributeCode
     * @param string $ergonodeType
     * @param array<int|string, int> $optionIds
     * @return AttributeStateInterface
     */
    public function build(
        string $ergonodeAttributeCode,
        string $magentoAttributeCode,
        string $ergonodeType,
        array $optionIds
    ): AttributeStateInterface;
}
