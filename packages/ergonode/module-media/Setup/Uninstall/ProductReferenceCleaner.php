<?php

declare(strict_types=1);

namespace Ergonode\Media\Setup\Uninstall;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class ProductReferenceCleaner
{
    private const string FILE_USAGE_TABLE = 'ergonode_media_file_usage';
    private const string GALLERY_USAGE_TABLE = 'ergonode_media_product_usage';

    public function __construct(
        private readonly Config $eav,
        private readonly ProductResource $products
    ) {
    }

    public function execute(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        if ($connection->isTableExists($setup->getTable(self::FILE_USAGE_TABLE))) {
            $this->clearFileAttributeValues($setup, $connection);
        }
        if ($connection->isTableExists($setup->getTable(self::GALLERY_USAGE_TABLE))) {
            $this->removeGalleryLinks($setup, $connection);
        }
    }

    private function clearFileAttributeValues(
        SchemaSetupInterface $setup,
        AdapterInterface $connection
    ): void {
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($setup->getTable(self::FILE_USAGE_TABLE), [
                    'product_id',
                    'attribute_code',
                    'store_id',
                    'attached_path',
                ])
                ->where('attached_path IS NOT NULL')
        );
        foreach ($rows as $row) {
            $attribute = $this->eav->getAttribute(Product::ENTITY, (string)$row['attribute_code']);
            $attributeId = (int)$attribute->getAttributeId();
            $backendTable = (string)$attribute->getBackendTable();
            if ($attributeId < 1 || $backendTable === '' || $attribute->getBackendType() === 'static') {
                continue;
            }
            $backendTable = $setup->getTable($backendTable);
            if (!$connection->isTableExists($backendTable)) {
                continue;
            }
            $linkField = $this->products->getLinkField();
            $linkValue = $this->productLinkValue($connection, (int)$row['product_id'], $linkField);
            if ($linkValue === null) {
                continue;
            }
            $connection->delete($backendTable, [
                'attribute_id = ?' => $attributeId,
                $linkField . ' = ?' => $linkValue,
                'store_id = ?' => (int)$attribute->getData('is_global') === 0 ? (int)$row['store_id'] : 0,
                'value = ?' => (string)$row['attached_path'],
            ]);
        }
    }

    private function removeGalleryLinks(SchemaSetupInterface $setup, AdapterInterface $connection): void
    {
        $galleryTable = $setup->getTable('catalog_product_entity_media_gallery');
        $valueTable = $setup->getTable('catalog_product_entity_media_gallery_value');
        $relationTable = $setup->getTable('catalog_product_entity_media_gallery_value_to_entity');
        foreach ([$galleryTable, $valueTable, $relationTable] as $table) {
            if (!$connection->isTableExists($table)) {
                return;
            }
        }
        $attributeId = (int)$this->eav
            ->getAttribute(Product::ENTITY, ProductInterface::MEDIA_GALLERY)
            ->getAttributeId();
        if ($attributeId < 1) {
            return;
        }
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($setup->getTable(self::GALLERY_USAGE_TABLE), ['product_id', 'attached_path'])
                ->where('attached_path IS NOT NULL')
        );
        foreach ($rows as $row) {
            $galleryValue = $this->galleryValue((string)$row['attached_path']);
            if ($galleryValue === null) {
                continue;
            }
            $valueIds = $connection->fetchCol(
                $connection->select()
                    ->from($galleryTable, ['value_id'])
                    ->where('attribute_id = ?', $attributeId)
                    ->where('BINARY value = BINARY ?', $galleryValue)
            );
            foreach ($valueIds as $valueId) {
                $valueId = (int)$valueId;
                $productId = (int)$row['product_id'];
                $condition = ['value_id = ?' => $valueId, 'entity_id = ?' => $productId];
                $connection->delete($relationTable, $condition);
                $connection->delete($valueTable, $condition);
                if ($this->hasGalleryRelations($connection, $relationTable, $valueId)) {
                    continue;
                }
                $connection->delete($valueTable, ['value_id = ?' => $valueId]);
                $connection->delete($galleryTable, ['value_id = ?' => $valueId]);
            }
        }
    }

    private function productLinkValue(
        AdapterInterface $connection,
        int $productId,
        string $linkField
    ): ?int {
        if ($linkField === $this->products->getIdFieldName()) {
            return $productId;
        }
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->products->getEntityTable(), [$linkField])
                ->where($this->products->getIdFieldName() . ' = ?', $productId)
                ->limit(1)
        );

        return $value === false ? null : (int)$value;
    }

    private function galleryValue(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $prefix = 'catalog/product/';
        if (!str_starts_with($path, $prefix) || str_contains($path, '..')) {
            return null;
        }

        return '/' . substr($path, strlen($prefix));
    }

    private function hasGalleryRelations(
        AdapterInterface $connection,
        string $relationTable,
        int $valueId
    ): bool {
        return $connection->fetchOne(
            $connection->select()
                ->from($relationTable, ['value_id'])
                ->where('value_id = ?', $valueId)
                ->limit(1)
        ) !== false;
    }
}
