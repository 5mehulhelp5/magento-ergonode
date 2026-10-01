<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Readiness;

use Magento\Framework\App\ResourceConnection;

class ProductAttributeSetUsageProvider
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /** @param string[] $selectedSkus @return array<int, int> */
    public function getUsage(array $selectedSkus = []): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('catalog_product_entity'),
                ['attribute_set_id', 'product_count' => 'COUNT(*)']
            )
            ->where('attribute_set_id > ?', 0)
            ->group('attribute_set_id')
            ->order('attribute_set_id ASC');
        if ($selectedSkus !== []) {
            $select->where('sku IN (?)', $selectedSkus);
        }

        $usage = [];
        foreach ($connection->fetchAll($select) as $row) {
            $attributeSetId = (int)$row['attribute_set_id'];
            if ($attributeSetId > 0) {
                $usage[$attributeSetId] = (int)$row['product_count'];
            }
        }

        return $usage;
    }
}
