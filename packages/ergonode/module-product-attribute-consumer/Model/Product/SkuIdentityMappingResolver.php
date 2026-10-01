<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Product;

use Ergonode\ProductAttribute\Api\SkuIdentityMappingProviderInterface;
use Ergonode\ProductAttributeConsumer\Api\SkuIdentityMappingResolverInterface;
use Ergonode\AttributeConsumer\Model\Provider\ErgonodeAttributeProvider;
use Magento\Framework\Exception\LocalizedException;

class SkuIdentityMappingResolver implements SkuIdentityMappingResolverInterface
{
    public function __construct(
        private readonly SkuIdentityMappingProviderInterface $mappingProvider,
        private readonly ErgonodeAttributeProvider $attributeProvider
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function resolve(): array
    {
        $mapping = $this->mappingProvider->getMapping();
        if ($mapping === null) {
            throw new LocalizedException(__(
                'Assigned Ergonode SKU mode requires exactly one complete attribute mapping to Magento SKU.'
            ));
        }
        $attribute = $this->attributeProvider->getAttribute((string)$mapping['ergonode_attribute_code']);
        if ($attribute === null
            || strtolower((string)$attribute['type']) !== 'text'
            || strtolower((string)$attribute['scope']) !== 'global'
            || empty($attribute['parameters']['unique'])
        ) {
            throw new LocalizedException(__(
                'The attribute mapped to Magento SKU must be an active, unique Ergonode Global Text attribute.'
            ));
        }
        if (empty($attribute['active'])) {
            throw new LocalizedException(__('The Ergonode attribute mapped to Magento SKU is inactive.'));
        }

        return $mapping;
    }
}
