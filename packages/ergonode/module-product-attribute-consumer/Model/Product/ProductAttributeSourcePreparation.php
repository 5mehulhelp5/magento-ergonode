<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Product;

use Ergonode\AttributeConsumer\Api\AttributeDefinitionSynchronizationInterface;
use Ergonode\ProductAttributeConsumer\Api\ProductAttributeSourcePreparationInterface;

class ProductAttributeSourcePreparation implements ProductAttributeSourcePreparationInterface
{
    public function __construct(
        private readonly AttributeDefinitionSynchronizationInterface $synchronization,
        private readonly ProductMappingProvider $mappingProvider
    ) {
    }

    public function prepare(): void
    {
        $this->synchronization->synchronize();
        $this->mappingProvider->reset();
    }
}
