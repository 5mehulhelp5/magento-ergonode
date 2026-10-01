<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Setup\Uninstall;

use Magento\Catalog\Model\Product;
use Magento\Framework\Setup\SchemaSetupInterface;
use PackHauer\FileAttribute\Model\Attribute\Backend\File;

class ProductAttributeCleaner
{
    private const string INPUT_TYPE = 'file';

    private const array BACKEND_MODELS = [
        File::class,
    ];

    public function execute(SchemaSetupInterface $setup): AttributeCleanup
    {
        $connection = $setup->getConnection();
        $attributeTable = $setup->getTable('eav_attribute');
        $entityTypeTable = $setup->getTable('eav_entity_type');
        if (!$connection->isTableExists($attributeTable) || !$connection->isTableExists($entityTypeTable)) {
            return new AttributeCleanup([], []);
        }

        $attributes = $connection->fetchAll(
            $connection->select()
                ->from(['attribute' => $attributeTable], ['attribute_id', 'attribute_code'])
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
        if ($attributes === []) {
            return new AttributeCleanup([], $this->getPreservedPaths($setup, []));
        }

        $attributeIds = array_map(static fn(array $attribute): int => (int)$attribute['attribute_id'], $attributes);
        $attributeCodes = array_values(array_map(
            static fn(array $attribute): string => (string)$attribute['attribute_code'],
            $attributes
        ));
        $preservedPaths = $this->getPreservedPaths($setup, $attributeIds);
        $connection->delete($attributeTable, ['attribute_id IN (?)' => $attributeIds]);

        return new AttributeCleanup($attributeCodes, $preservedPaths);
    }

    /**
     * @param list<int> $removedAttributeIds
     * @return list<string>
     */
    private function getPreservedPaths(SchemaSetupInterface $setup, array $removedAttributeIds): array
    {
        $connection = $setup->getConnection();
        $valueTable = $setup->getTable('catalog_product_entity_varchar');
        if (!$connection->isTableExists($valueTable)) {
            return [];
        }

        $select = $connection->select()
            ->distinct()
            ->from($valueTable, ['value'])
            ->where('value LIKE ?', 'catalog/product/files/%');
        if ($removedAttributeIds !== []) {
            $select->where('attribute_id NOT IN (?)', $removedAttributeIds);
        }

        $paths = [];
        foreach ($connection->fetchCol($select) as $path) {
            $normalizedPath = $this->normalizeMediaPath((string)$path);
            if ($normalizedPath !== null) {
                $paths[] = $normalizedPath;
            }
        }

        return array_values(array_unique($paths));
    }

    private function normalizeMediaPath(string $path): ?string
    {
        $path = preg_replace('#/+#', '/', trim(str_replace('\\', '/', $path), '/')) ?? '';
        if ($path === '' || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1) {
            return null;
        }

        return $path;
    }

    private function legacyBackendModel(): string
    {
        return implode('\\', ['Vendivo', 'FileAttribute', 'Model', 'Attribute', 'Backend', 'File']);
    }
}
