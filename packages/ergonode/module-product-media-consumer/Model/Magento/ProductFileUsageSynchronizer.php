<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Magento;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Media\Api\FileUsageRecorderInterface;
use Ergonode\Media\Model\ValueObject\File\FileUsageReference;
use Ergonode\Media\Model\ValueObject\File\FileUsageSet;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use LogicException;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;

class ProductFileUsageSynchronizer
{
    public function __construct(
        private readonly ProductAttributeMappingProviderInterface $mappings,
        private readonly LanguageStoreMappingProviderInterface $languages,
        private readonly FileUsageRecorderInterface $recorder,
        private readonly Config $eav
    ) {
    }

    /** @param RemoteProductAttribute[] $attributes */
    public function synchronize(int $productId, array $attributes): void
    {
        $source = [];
        foreach ($attributes as $attribute) {
            $source[$attribute->code] = $attribute;
        }
        $storeLanguages = $this->languages->getLanguageStoreMap();
        $references = [];
        foreach ($this->mappings->getMappings() as $mapping) {
            $sourceType = strtolower((string)$mapping['ergonode_type']);
            $targetType = strtolower((string)$mapping['magento_type']);
            if (!in_array($sourceType, ['image', 'file'], true) || $sourceType !== $targetType) {
                continue;
            }
            $item = $source[(string)$mapping['ergonode_attribute_code']] ?? null;
            if ($item === null) {
                continue;
            }
            if (!$item->values instanceof LocalizedStringValues) {
                throw new LogicException('Remote file attribute must contain localized path values.');
            }
            $attribute = $this->eav->getAttribute(Product::ENTITY, (string)$mapping['magento_attribute_code']);
            $stores = (int)$attribute->getIsGlobal() !== 0 ? [0 => $storeLanguages[0] ?? null] : $storeLanguages;
            foreach ($stores as $storeId => $language) {
                $path = $language === null ? null : ($item->values->all()[$language] ?? null);
                $path = trim((string)$path);
                if ($path !== '') {
                    $references[] = new FileUsageReference(
                        $path,
                        (string)$mapping['magento_attribute_code'],
                        (int)$storeId
                    );
                }
            }
        }
        $this->recorder->synchronize($productId, new FileUsageSet($references));
    }
}
