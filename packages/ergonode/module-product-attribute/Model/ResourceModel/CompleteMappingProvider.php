<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\ResourceModel;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductAttribute\Model\Mapping\ValueAdapterRegistry;
use Magento\Framework\App\ResourceConnection;

class CompleteMappingProvider implements CompleteMappingProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductAttributePolicy $attributePolicy,
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly ValueAdapterRegistry $valueAdapters
    ) {
    }

    public function getMappings(?string $direction = null): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName('ergonode_product_attribute_mapping'))
                ->where('ergonode_attribute_code IS NOT NULL')
                ->where('magento_attribute_code IS NOT NULL')
                ->where('status = ?', 'complete')
                ->order('sort_order ASC')
                ->order('mapping_id ASC')
        );
        $mappings = [];
        foreach ($rows as $row) {
            $source = (string)$row['ergonode_attribute_code'];
            $target = (string)$row['magento_attribute_code'];
            if (!$this->attributePolicy->isTypeMappable((string)$row['ergonode_type'])
                || !$this->attributePolicy->isErgonodeMappable($source)
                || !$this->attributePolicy->isMappable($target)
            ) {
                continue;
            }
            $adapter = $row['value_adapter'] ?? null;
            if ($direction !== null && !$this->valueAdapters->isAvailable($adapter, $target, $direction)) {
                continue;
            }
            $id = (int)$row['mapping_id'];
            $mappings[$id] = [
                'mapping_id' => $id,
                'ergonode_attribute_code' => $source,
                'magento_attribute_code' => $target,
                'ergonode_type' => $this->typeResolver->fromConsumerType((string)$row['ergonode_type']),
                'magento_type' => (string)$row['magento_type'],
                'value_adapter' => $adapter,
                'option_ids' => [],
            ];
        }
        if ($mappings === []) {
            return [];
        }
        $options = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName('ergonode_product_option_mapping'))
                ->where('attribute_mapping_id IN (?)', array_keys($mappings))
                ->where('ergonode_option_code IS NOT NULL')
                ->where('magento_option_id IS NOT NULL')
                ->where('status = ?', 'complete')
        );
        foreach ($options as $option) {
            $id = (int)$option['attribute_mapping_id'];
            $code = (string)$option['ergonode_option_code'];
            if (isset($mappings[$id]) && $code !== '') {
                $mappings[$id]['option_ids'][$code] = (int)$option['magento_option_id'];
            }
        }

        return $mappings;
    }
}
