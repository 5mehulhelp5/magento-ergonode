<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Ergonode\ProductAttributeConsumer\Api\SkuIdentityMappingResolverInterface;
use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Framework\Exception\LocalizedException;

class MappedMagentoSkuResolver
{
    public function __construct(
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        private readonly SkuIdentityMappingResolverInterface $mappingResolver
    ) {
    }

    public function getConfiguredMode(): string
    {
        $mode = $this->identityModeProvider->getMode();
        $this->identityModeProvider->assertNewModeAvailable($mode);

        return $mode;
    }

    public function resolve(RemoteProduct $source, string $identityMode): string
    {
        if ($identityMode === ProductIdentityInterface::MODE_SHARED) {
            return $source->sku;
        }
        $mapping = $this->mappingResolver->resolve();
        $attributeCode = (string)$mapping['ergonode_attribute_code'];
        $candidates = [];
        foreach ($source->attributes as $attribute) {
            if ($attribute->code !== $attributeCode) {
                continue;
            }
            foreach ($attribute->values->all() as $value) {
                if (is_array($value)) {
                    continue;
                }
                $value = trim((string)$value);
                if ($value !== '') {
                    $candidates[$value] = $value;
                }
            }
        }
        if (count($candidates) !== 1) {
            throw new LocalizedException(__(
                'Ergonode product "%1" must contain one non-empty global Magento SKU value in attribute "%2".',
                $source->sku,
                $attributeCode
            ));
        }

        return array_values($candidates)[0];
    }
}
