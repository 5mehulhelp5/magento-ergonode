<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class CategoryAttributeWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CategoryResource $categoryResource,
        private readonly EavConfig $eavConfig,
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryAttributeValueMapper $valueMapper
    ) {
    }

    /**
     * @param array<int, array{code: string, type: string, values: array<string, mixed>}> $attributes
     */
    public function writeMappedValues(int $categoryId, array $attributes): int
    {
        if ($categoryId <= 0) {
            return 0;
        }

        $written = 0;
        $linkField = $this->categoryResource->getLinkField();
        $linkValue = $this->resolveLinkValue($categoryId, $linkField);
        $mapped = $this->valueMapper->mapForSynchronization($attributes);
        $attributeCodes = array_values(array_unique([
            ...array_keys($mapped['values']),
            ...array_keys($mapped['clear']),
        ]));
        foreach ($attributeCodes as $attributeCode) {
            $values = $mapped['values'][$attributeCode] ?? [];
            $clearStoreIds = $mapped['clear'][$attributeCode] ?? [];
            $attribute = $this->eavConfig->getAttribute(Category::ENTITY, $attributeCode);
            $attributeId = (int)$attribute->getAttributeId();
            $backendTable = trim((string)$attribute->getBackendTable());
            if ($attributeId <= 0 || $backendTable === '') {
                continue;
            }
            if ((int)$attribute->getIsGlobal() !== 0) {
                $values = isset($values[0]) ? [0 => $values[0]] : [];
                $clearStoreIds = $values === [] && in_array(0, $clearStoreIds, true) ? [0] : [];
            }
            $storeIds = array_values(array_unique([
                ...array_map('intval', array_keys($values)),
                ...array_map('intval', $clearStoreIds),
            ]));
            if ($storeIds === []) {
                continue;
            }

            $backendTable = $this->resourceConnection->getTableName($backendTable);
            $existing = $this->getConnection()->fetchPairs(
                $this->getConnection()->select()
                    ->from($backendTable, ['store_id', 'value'])
                    ->where('attribute_id = ?', $attributeId)
                    ->where($linkField . ' = ?', $linkValue)
                    ->where('store_id IN (?)', $storeIds)
            );
            $rows = [];
            foreach ($values as $storeId => $value) {
                if (array_key_exists($storeId, $existing) && (string)$existing[$storeId] === (string)$value) {
                    continue;
                }
                $rows[] = [
                    'attribute_id' => $attributeId,
                    'store_id' => (int)$storeId,
                    $linkField => $linkValue,
                    'value' => $value,
                ];
            }
            if ($rows !== []) {
                $this->getConnection()->insertOnDuplicate($backendTable, $rows, ['value']);
                $written += count($rows);
            }
            $clearStoreIds = array_values(array_diff(array_map('intval', $clearStoreIds), array_map(
                'intval',
                array_keys($values)
            )));
            $clearStoreIds = array_values(array_intersect(
                $clearStoreIds,
                array_map('intval', array_keys($existing))
            ));
            if ($clearStoreIds !== []) {
                $written += $this->getConnection()->delete($backendTable, [
                    'attribute_id = ?' => $attributeId,
                    $linkField . ' = ?' => $linkValue,
                    'store_id IN (?)' => $clearStoreIds,
                ]);
            }
        }
        if ($written > 0) {
            $this->categoryRepository->_resetState();
        }

        return $written;
    }

    private function resolveLinkValue(int $categoryId, string $linkField): int
    {
        $idField = $this->categoryResource->getIdFieldName();
        if ($linkField === $idField) {
            return $categoryId;
        }

        return (int)$this->getConnection()->fetchOne(
            $this->getConnection()
                ->select()
                ->from($this->categoryResource->getEntityTable(), [$linkField])
                ->where($idField . ' = ?', $categoryId)
                ->limit(1)
        );
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
