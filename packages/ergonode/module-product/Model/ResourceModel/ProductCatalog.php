<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\ResourceModel;

use Ergonode\Product\Api\ProductCatalogInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class ProductCatalog implements ProductCatalogInterface
{
    private const array COLUMNS = [
        'product_id' => 'entity_id', 'sku' => 'sku', 'ergonode_sku' => 'ergonode_sku',
        'name' => 'name', 'attribute_set_name' => 'attribute_set_name', 'type_id' => 'type_id',
        'attribute_set_id' => 'attribute_set_id',
    ];

    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function getPage(string $search, int $page, int $pageSize, array $criteria = []): array
    {
        $collection = $this->collection($search, $criteria);
        $total = $collection->getSize();
        $collection->addAttributeToSelect(['name', 'thumbnail']);
        $pageSize = max(1, min(100, $pageSize));
        $page = max(1, min($page, (int)ceil($total / $pageSize)));
        $collection->setPageSize($pageSize)->setCurPage($page);
        $items = [];
        foreach ($collection as $product) {
            $productId = (int)$product->getId();
            $items[] = [
                'product_id' => $productId,
                'sku' => (string)$product->getSku(),
                'ergonode_sku' => (string)$product->getData('ergonode_sku'),
                'name' => (string)$product->getName(),
                'thumbnail' => (string)$product->getThumbnail(),
                'attribute_set_id' => (int)$product->getAttributeSetId(),
                'attribute_set_name' => (string)$product->getData('attribute_set_name'),
                'type_id' => (string)$product->getTypeId(),
            ];
        }

        return ['total' => $total, 'page' => $page, 'items' => $items, 'filter_options' => $this->filterOptions()];
    }

    /**
     * @return array{attribute_set_id: list<array{value: string, label: string}>,
     *     type_id: list<array{value: string, label: string}>}
     */
    private function filterOptions(): array
    {
        $collection = $this->collectionFactory->create();
        $connection = $collection->getConnection();
        $select = $connection->select()
            ->from(['product' => $collection->getTable('catalog_product_entity')], ['attribute_set_id', 'type_id'])
            ->joinInner(
                ['attribute_set' => $collection->getTable('eav_attribute_set')],
                'attribute_set.attribute_set_id = product.attribute_set_id',
                ['attribute_set_name']
            )
            ->distinct()
            ->order(['attribute_set_name ASC', 'type_id ASC']);
        $sets = [];
        $types = [];
        foreach ($connection->fetchAll($select) as $row) {
            $setId = (string)$row['attribute_set_id'];
            $type = (string)$row['type_id'];
            $sets[$setId] = ['value' => $setId, 'label' => (string)$row['attribute_set_name']];
            $types[$type] = ['value' => $type, 'label' => $type];
        }
        ksort($types);

        return ['attribute_set_id' => array_values($sets), 'type_id' => array_values($types)];
    }

    public function getIds(string $search, array $selected, array $excluded, bool $all, array $criteria = []): array
    {
        if (!$all && $selected === []) {
            return [];
        }
        $collection = $this->collection($all ? $search : '', $all ? $criteria : []);
        if (!$all || $excluded !== []) {
            $collection->addIdFilter($all ? $excluded : $selected, $all);
        }

        $ids = array_map('intval', $collection->getAllIds());
        sort($ids);

        return $ids;
    }

    /** @param array<string, mixed> $criteria */
    private function collection(string $search, array $criteria): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId(0);
        $collection->joinField(
            'attribute_set_name',
            'eav_attribute_set',
            'attribute_set_name',
            'attribute_set_id=attribute_set_id',
            null,
            'left'
        );
        $collection->joinField(
            'ergonode_sku',
            'ergonode_product_mapping',
            'ergonode_sku',
            'product_id=entity_id',
            null,
            'left'
        );
        $this->applyCriteria($collection, $criteria);
        $search = trim($search);
        if ($search !== '') {
            $conditions = [
                ['attribute' => 'sku', 'like' => '%' . $search . '%'],
                ['attribute' => 'name', 'like' => '%' . $search . '%'],
            ];
            if (ctype_digit($search)) {
                $conditions[] = ['attribute' => 'entity_id', 'eq' => (int)$search];
            }
            $collection->addAttributeToFilter($conditions);
        }

        return $collection;
    }

    /** @param array<string, mixed> $criteria */
    private function applyCriteria(Collection $collection, array $criteria): void
    {
        foreach ($criteria as $key => $value) {
            if (!is_string($value) || (!isset(self::COLUMNS[$key]) && !in_array($key, ['sort', 'direction'], true))) {
                throw new LocalizedException(__('Invalid product grid criteria.'));
            }
        }
        foreach (self::COLUMNS as $key => $attribute) {
            $value = trim($criteria[$key] ?? '');
            if ($value === '') {
                continue;
            }
            $numericId = in_array($key, ['product_id', 'attribute_set_id'], true);
            if ($numericId && (!ctype_digit($value) || (int)$value < 1)) {
                throw new LocalizedException(__('Product and attribute set IDs must be positive integers.'));
            }
            $condition = $numericId || $key === 'type_id'
                ? ['eq' => $numericId ? (int)$value : $value]
                : ['like' => '%' . $value . '%'];
            $collection->addAttributeToFilter($attribute, $condition);
        }
        $sort = $criteria['sort'] ?? 'product_id';
        $direction = strtoupper($criteria['direction'] ?? 'ASC');
        if (!isset(self::COLUMNS[$sort]) || !in_array($direction, ['ASC', 'DESC'], true)) {
            throw new LocalizedException(__('Invalid product grid sorting.'));
        }
        $collection->addAttributeToSort(self::COLUMNS[$sort], $direction);
        if ($sort !== 'product_id') {
            $collection->addAttributeToSort('entity_id', 'ASC');
        }
    }
}
