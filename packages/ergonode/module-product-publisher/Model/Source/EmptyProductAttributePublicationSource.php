<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Api\ProductAttributePublicationSourceInterface;
use Ergonode\ProductPublisher\Model\Data\ProductAttributeValuesResultDto;
use Magento\Catalog\Model\Product;

class EmptyProductAttributePublicationSource implements ProductAttributePublicationSourceInterface
{
    public function getMappings(): array
    {
        return [];
    }

    public function getValues(Product $product, array $storeProducts, array $mappings): ProductAttributeValuesResultDto
    {
        return new ProductAttributeValuesResultDto([]);
    }
}
