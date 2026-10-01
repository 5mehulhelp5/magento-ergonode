<?php

declare(strict_types=1);

namespace Ergonode\Template\Model\Mapping;

use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Magento\Framework\App\ResourceConnection;

class ProductAttributeSetProvider implements ProductAttributeSetProviderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getProductAttributeSets(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    ['sets' => $this->resourceConnection->getTableName('eav_attribute_set')],
                    ['id' => 'attribute_set_id', 'name' => 'attribute_set_name']
                )
                ->joinInner(
                    ['types' => $this->resourceConnection->getTableName('eav_entity_type')],
                    'types.entity_type_id = sets.entity_type_id',
                    []
                )
                ->where('types.entity_type_code = ?', 'catalog_product')
                ->order('sets.attribute_set_name ASC')
        );

        return array_map(static fn (array $row): array => [
            'id' => (int)$row['id'], 'name' => (string)$row['name'],
        ], $rows);
    }
}
