<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Setup\Uninstall;

use Magento\Catalog\Model\Product;
use Magento\Framework\Setup\SchemaSetupInterface;
use PackHauer\UnitAttribute\Model\Attribute\Backend\Unit;

class ProductAttributeCleaner
{
    private const string INPUT_TYPE = 'unit';

    private const array BACKEND_MODELS = [
        Unit::class,
    ];

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $attributeTable = $setup->getTable('eav_attribute');
        $entityTypeTable = $setup->getTable('eav_entity_type');
        if (!$connection->isTableExists($attributeTable) || !$connection->isTableExists($entityTypeTable)) {
            return;
        }

        $attributeIds = $connection->fetchCol(
            $connection->select()
                ->from(['attribute' => $attributeTable], ['attribute_id'])
                ->join(
                    ['entity_type' => $entityTypeTable],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('entity_type.entity_type_code = ?', Product::ENTITY)
                ->where(
                    sprintf(
                        '(%s OR %s)',
                        $connection->quoteInto('attribute.frontend_input = ?', self::INPUT_TYPE),
                        $connection->quoteInto(
                            'attribute.backend_model IN (?)',
                            [...self::BACKEND_MODELS, $this->legacyBackendModel()]
                        )
                    )
                )
        );
        if ($attributeIds === []) {
            return;
        }

        $connection->delete($attributeTable, ['attribute_id IN (?)' => array_map('intval', $attributeIds)]);
    }

    private function legacyBackendModel(): string
    {
        return implode('\\', ['Vendivo', 'UnitAttribute', 'Model', 'Attribute', 'Backend', 'Unit']);
    }
}
