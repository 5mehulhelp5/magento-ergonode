<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\ResourceModel;

use Ergonode\Product\Api\ProductStateSnapshotInterface;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\App\ResourceConnection;

/** Batch queries inspect database values only, never image bytes or indexed/volatile data. */
class ProductStateSnapshot implements ProductStateSnapshotInterface
{
    public function __construct(private readonly ResourceConnection $resource, private readonly Product $product)
    {
    }

    public function get(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $db = $this->resource->getConnection();
        $entity = $this->resource->getTableName('catalog_product_entity');
        $link = $this->product->getLinkField();
        $state = [];
        foreach ($db->fetchAll($db->select()->from($entity)->where('entity_id IN (?)', $productIds)->order('entity_id')) as $row) {
            $id = (int)$row['entity_id'];
            unset($row['entity_id'], $row['row_id'], $row['created_at'], $row['updated_at'], $row['created_in'], $row['updated_in']);
            ksort($row);
            $state[$id]['static'] = $row;
        }
        foreach (['datetime', 'decimal', 'int', 'text', 'varchar'] as $type) {
            $select = $db->select()->from(['v' => $this->resource->getTableName('catalog_product_entity_' . $type)],
                ['attribute_id', 'store_id', 'value'])
                ->joinInner(['p' => $entity], 'p.' . $link . ' = v.' . $link, ['entity_id'])
                ->where('p.entity_id IN (?)', $productIds)->order(['p.entity_id', 'v.attribute_id', 'v.store_id']);
            foreach ($db->fetchAll($select) as $row) {
                $id = (int)$row['entity_id'];
                unset($row['entity_id']);
                $state[$id][$type][] = $row;
            }
        }
        foreach (['catalog_product_website' => ['product_id', ['website_id']],
            'catalog_category_product' => ['product_id', ['category_id', 'position']],
            'catalog_product_relation' => ['parent_id', ['child_id']]] as $table => [$field, $columns]) {
            $select = $db->select()->from($this->resource->getTableName($table), [$field, ...$columns])
                ->where($field . ' IN (?)', $productIds)->order([$field, ...$columns]);
            foreach ($db->fetchAll($select) as $row) {
                $id = (int)$row[$field];
                unset($row[$field]);
                $state[$id][$table][] = $row;
            }
        }
        return $state;
    }
}
