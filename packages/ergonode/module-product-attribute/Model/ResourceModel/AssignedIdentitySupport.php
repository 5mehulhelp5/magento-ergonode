<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\ResourceModel;

use Ergonode\Product\Api\AssignedIdentitySupportInterface;
use Ergonode\ProductAttribute\Api\SkuIdentityMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class AssignedIdentitySupport implements AssignedIdentitySupportInterface, SkuIdentityMappingProviderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getMapping(): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $mappings = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_product_attribute_mapping'),
                    ['mapping_id', 'ergonode_attribute_code', 'ergonode_type', 'magento_type']
                )
                ->where('status = ?', 'complete')
                ->where('magento_attribute_code = ?', 'sku')
                ->where('ergonode_attribute_code IS NOT NULL')
        );
        if (count($mappings) !== 1
            || trim((string)$mappings[0]['ergonode_attribute_code']) === ''
            || strtolower((string)$mappings[0]['ergonode_type']) !== 'text'
        ) {
            return null;
        }

        return [
            'mapping_id' => (int)$mappings[0]['mapping_id'],
            'ergonode_attribute_code' => (string)$mappings[0]['ergonode_attribute_code'],
            'magento_attribute_code' => 'sku',
            'ergonode_type' => 'text',
            'magento_type' => (string)$mappings[0]['magento_type'],
            'option_ids' => [],
        ];
    }

    public function validate(): void
    {
        if ($this->getMapping() === null) {
            throw new LocalizedException(__(
                'Independent Ergonode SKUs require exactly one complete Text attribute mapping to Magento SKU.'
            ));
        }
    }
}
