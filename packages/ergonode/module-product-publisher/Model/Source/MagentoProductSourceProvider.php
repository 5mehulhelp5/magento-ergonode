<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Source;

use Ergonode\ProductPublisher\Api\ProductAttributePublicationSourceInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\ProductPublisher\Api\ProductTemplateCodeProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;

class MagentoProductSourceProvider
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductAttributePublicationSourceInterface $attributeSource,
        private readonly ProductTemplateCodeProviderInterface $templateCodeProvider,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /** @param string[] $selectedSkus */
    public function load(array $selectedSkus = []): ProductSourceData
    {
        $mappings = $this->attributeSource->getMappings();
        $attributeCodes = array_values(array_unique(array_column($mappings, 'magento_attribute_code')));
        $products = $this->baseProducts($attributeCodes, $selectedSkus);
        $attributeSetIds = array_values(array_unique(array_map(
            static fn (Product $product): int => (int)$product->getAttributeSetId(),
            $products
        )));
        $templateCodes = $this->templateCodeProvider->getTemplateCodesByAttributeSetIds($attributeSetIds);

        return new ProductSourceData(
            $products,
            $this->storeProducts(array_keys($products), $attributeCodes),
            $mappings,
            $templateCodes,
            $this->attributeCodesBySet($attributeSetIds, $mappings)
        );
    }

    /** @param string[] $attributeCodes @param string[] $selectedSkus @return array<string, Product> */
    private function baseProducts(array $attributeCodes, array $selectedSkus): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId(0);
        $collection->addAttributeToSelect($attributeCodes);
        if ($selectedSkus !== []) {
            $collection->addFieldToFilter('sku', ['in' => $selectedSkus]);
        }
        $result = [];
        foreach ($collection as $product) {
            if ($product instanceof Product && trim((string)$product->getSku()) !== '') {
                $result[trim((string)$product->getSku())] = $product;
            }
        }
        return $result;
    }

    /** @param string[] $skus @param string[] $attributeCodes @return array<int, array<string, Product>> */
    private function storeProducts(array $skus, array $attributeCodes): array
    {
        if ($skus === [] || $attributeCodes === []) {
            return [];
        }
        $result = [];
        foreach (array_keys($this->languageMappingProvider->getLanguageStoreMap()) as $storeId) {
            if ($storeId === 0) {
                continue;
            }
            $collection = $this->collectionFactory->create();
            $collection->setStoreId((int)$storeId);
            $collection->addAttributeToSelect($attributeCodes);
            $collection->addFieldToFilter('sku', ['in' => $skus]);
            foreach ($collection as $product) {
                if ($product instanceof Product) {
                    $result[(int)$storeId][(string)$product->getSku()] = $product;
                }
            }
        }

        return $result;
    }

    /**
     * @param int[] $attributeSetIds
     * @param array<int, array<string, mixed>> $mappings
     * @return array<int, string[]>
     */
    private function attributeCodesBySet(
        array $attributeSetIds,
        array $mappings
    ): array {
        $magentoAttributeCodes = array_values(array_unique(array_column($mappings, 'magento_attribute_code')));
        if ($attributeSetIds === [] || $magentoAttributeCodes === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['assignment' => $this->resourceConnection->getTableName('eav_entity_attribute')],
                    ['attribute_set_id']
                )
                ->join(
                    ['attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                    'attribute.attribute_id = assignment.attribute_id',
                    ['attribute_code']
                )
                ->where('assignment.attribute_set_id IN (?)', $attributeSetIds)
                ->where('attribute.attribute_code IN (?)', $magentoAttributeCodes)
        );
        $assignedCodesBySet = [];
        foreach ($rows as $row) {
            $assignedCodesBySet[(int)$row['attribute_set_id']][(string)$row['attribute_code']] = true;
        }
        $result = [];
        foreach ($attributeSetIds as $attributeSetId) {
            foreach ($mappings as $mapping) {
                $magentoAttributeCode = (string)$mapping['magento_attribute_code'];
                $ergonodeAttributeCode = (string)$mapping['ergonode_attribute_code'];
                if (isset($assignedCodesBySet[$attributeSetId][$magentoAttributeCode])) {
                    $result[$attributeSetId][] = $ergonodeAttributeCode;
                }
            }
            $result[$attributeSetId] = array_values(array_unique($result[$attributeSetId] ?? []));
            sort($result[$attributeSetId]);
        }

        return $result;
    }
}
