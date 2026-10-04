<?php

declare(strict_types=1);

namespace Ergonode\ProductMediaConsumer\Model\Magento;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Media\Api\FileUsageRecorderInterface;
use Ergonode\Media\Model\ValueObject\File\FileUsageReference;
use Ergonode\Media\Model\ValueObject\File\FileUsageSet;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\Attribute\LocalizedStringValues;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProductAttribute;
use LogicException;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Store\Model\StoreManagerInterface;

class ProductFileUsageSynchronizer
{
    public function __construct(
        private readonly ProductAttributeMappingProviderInterface $mappings,
        private readonly LanguageStoreMappingProviderInterface $languages,
        private readonly FileUsageRecorderInterface $recorder,
        private readonly Config $eav,
        private readonly AsynchronousFileAttributeMapping $deferredMappings,
        private readonly StoreManagerInterface $storeManager,
        private readonly ImageRolesInterface $imageRoles
    ) {
    }

    /** @param RemoteProductAttribute[] $attributes */
    public function synchronize(int $productId, array $attributes, bool $synchronizeImages = true, ?array $attributeCodes = null): void
    {
        $source = [];
        foreach ($attributes as $attribute) {
            $source[$attribute->code] = $attribute;
        }
        $storeLanguages = $this->languages->getLanguageStoreMap();
        $references = [];
        $preserved = $synchronizeImages ? [] : array_keys($this->imageRoles->getOptions());
        foreach ($this->mappings->getMappings() as $mapping) {
            if (!$this->deferredMappings->supports($mapping)) {
                continue;
            }
            $code = (string)$mapping['magento_attribute_code'];
            if (($attributeCodes !== null && !in_array($code, $attributeCodes, true))
                || in_array($code, $preserved, true)
            ) {
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
            $stores = $this->stores((int)$attribute->getIsGlobal(), $storeLanguages);
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
        $this->recorder->synchronize($productId, new FileUsageSet($references, $attributeCodes, $preserved));
    }

    /** @param array<int, string|null> $languages @return array<int, string|null> */
    private function stores(int $scope, array $languages): array
    {
        if ($scope === 1) {
            return [0 => $languages[0] ?? null];
        }
        if ($scope !== 2) {
            return $languages;
        }
        $result = [0 => $languages[0] ?? null];
        $websites = [];
        ksort($languages);
        foreach ($languages as $storeId => $language) {
            if ((int)$storeId === 0) {
                continue;
            }
            $website = $this->storeManager->getStore($storeId)->getWebsite();
            $websiteId = (int)$website->getId();
            if (isset($websites[$websiteId])) {
                continue;
            }
            $websites[$websiteId] = true;
            // Product Action propagates this single value to every store in the website.
            $defaultId = (int)$website->getDefaultStore()->getId();
            $selectedId = array_key_exists($defaultId, $languages) ? $defaultId : (int)$storeId;
            $result[$selectedId] = $languages[$selectedId];
        }
        return $result;
    }
}
