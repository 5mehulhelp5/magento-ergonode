<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\ProductAttribute\Api\ProductAttributeCodeMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;

class ProductAttributeCodeMappingProvider implements ProductAttributeCodeMappingProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductAttributePolicy $attributePolicy
    ) {
    }

    public function getMagentoAttributeCodes(array $ergonodeAttributeCodes): array
    {
        $ergonodeAttributeCodes = array_values(array_unique(array_filter(array_map(
            static fn (string $code): string => trim($code),
            $ergonodeAttributeCodes
        ))));
        if ($ergonodeAttributeCodes === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_product_attribute_mapping'),
                    ['ergonode_attribute_code', 'magento_attribute_code']
                )
                ->where('status = ?', 'complete')
                ->where('ergonode_attribute_code IN (?)', $ergonodeAttributeCodes)
                ->where('magento_attribute_code IS NOT NULL')
                ->order('sort_order ASC')
                ->order('mapping_id ASC')
        );

        $mappings = [];
        foreach ($rows as $row) {
            $ergonodeCode = trim((string)$row['ergonode_attribute_code']);
            $magentoCode = trim((string)$row['magento_attribute_code']);
            if ($ergonodeCode !== '' && $magentoCode !== '' && !isset($mappings[$ergonodeCode])
                && !$this->attributePolicy->isIdentityAttribute($magentoCode)
            ) {
                $mappings[$ergonodeCode] = $magentoCode;
            }
        }

        return $mappings;
    }
}
