<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\ResourceModel;

use Ergonode\CategoryConsumer\Api\CategoryNameWriterInterface;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\Exception\LocalizedException;

class CategoryNameWriter implements CategoryNameWriterInterface
{
    public function __construct(
        private readonly CategoryResource $resource,
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    /** @param array<int, string|null> $values */
    public function write(int $categoryId, string $attributeCode, array $values): int
    {
        $attribute = $this->resource->getAttribute($attributeCode);
        if (!$attribute || !(int)$attribute->getAttributeId() || $attribute->getFrontendInput() !== 'text'
            || $attribute->getBackendType() !== 'varchar' || $attribute->getSourceModel()
        ) {
            throw new LocalizedException(__('Choose a text field for the category name.'));
        }
        if ((int)$attribute->getIsGlobal() !== 0) {
            $values = array_intersect_key($values, [0 => true]);
        }
        if ($values === []) {
            return 0;
        }
        $connection = $this->resource->getConnection();
        $linkField = $this->resource->getLinkField();
        $linkValue = $connection->fetchOne($connection->select()
            ->from($this->resource->getEntityTable(), [$linkField])
            ->where($this->resource->getIdFieldName() . ' = ?', $categoryId));
        if (!$linkValue) {
            throw new LocalizedException(__('The mapped category no longer exists.'));
        }
        $table = $this->resource->getTable($attribute->getBackendTable());
        $attributeId = (int)$attribute->getAttributeId();
        $existing = $connection->fetchPairs($connection->select()->from($table, ['store_id', 'value'])
            ->where('attribute_id = ?', $attributeId)->where($linkField . ' = ?', $linkValue)
            ->where('store_id IN (?)', array_keys($values)));
        $changed = 0;
        foreach ($values as $storeId => $value) {
            if ($value === null) {
                $changed += $connection->delete($table, [
                    'attribute_id = ?' => $attributeId, $linkField . ' = ?' => $linkValue, 'store_id = ?' => $storeId,
                ]);
            } elseif (!array_key_exists($storeId, $existing) || (string)$existing[$storeId] !== $value) {
                $connection->insertOnDuplicate($table, [
                    'attribute_id' => $attributeId, $linkField => $linkValue, 'store_id' => $storeId, 'value' => $value,
                ], ['value']);
                $changed++;
            }
        }
        if ($changed > 0) {
            $this->categoryRepository->_resetState();
        }

        return $changed;
    }
}
