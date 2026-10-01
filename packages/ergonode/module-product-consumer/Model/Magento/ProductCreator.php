<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductCreator
{
    public function __construct(
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ProductAttributePolicy $attributePolicy
    ) {
    }

    /** @param array<string, array<int, float|int|string|string[]>> $initialAttributeValues */
    public function create(
        RemoteProduct $source,
        string $magentoType,
        int $attributeSetId,
        array $initialAttributeValues = [],
        ?string $magentoSku = null
    ): ProductInterface {
        $magentoSku = trim($magentoSku ?? $source->sku);
        $product = $this->productFactory->create();
        $product->setSku($magentoSku);
        $product->setTypeId($magentoType);
        $product->setAttributeSetId($attributeSetId);
        $product->setName($magentoSku);
        $manualValues = $this->attributePolicy->getManualCreationValues();
        $product->setStatus($manualValues['status'] ?? Status::STATUS_DISABLED);
        $product->setVisibility(
            $manualValues['visibility']
                ?? ($source->isVariant ? Visibility::VISIBILITY_NOT_VISIBLE : Visibility::VISIBILITY_BOTH)
        );
        $product->setPrice($manualValues['price'] ?? 0);
        $product->setTaxClassId(0);
        $product->setWebsiteIds($this->websiteIds());
        foreach ($initialAttributeValues as $attributeCode => $storeValues) {
            if (array_key_exists(0, $storeValues)) {
                $product->setData($attributeCode, $storeValues[0]);
            }
        }

        return $this->productRepository->save($product);
    }

    /** @return int[] */
    private function websiteIds(): array
    {
        $websiteIds = [];
        foreach (array_keys($this->languageMappingProvider->getLanguageStoreMap()) as $storeId) {
            if ((int)$storeId === 0) {
                continue;
            }
            $websiteId = (int)$this->storeManager->getStore((int)$storeId)->getWebsiteId();
            if ($websiteId > 0) {
                $websiteIds[] = $websiteId;
            }
        }

        return array_values(array_unique($websiteIds));
    }
}
