<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class ProductAttributeWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductResource $productResource,
        private readonly EavConfig $eavConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array<string, array<int, float|int|string|string[]>> $values
     * @param array<string, int[]> $clear
     * @return int
     */
    public function write(int $productId, array $values, array $clear = []): int
    {
        $written = 0;
        $linkField = $this->productResource->getLinkField();
        $linkValue = $this->resolveLinkValue($productId, $linkField);
        $codes = array_values(array_unique([...array_keys($values), ...array_keys($clear)]));
        foreach ($codes as $attributeCode) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
            $attributeId = (int)$attribute->getAttributeId();
            $backendTable = trim((string)$attribute->getBackendTable());
            if ($attributeId < 1 || $backendTable === '' || $attribute->getBackendType() === 'static') {
                continue;
            }
            $attributeValues = $values[$attributeCode] ?? [];
            $clearStoreIds = $clear[$attributeCode] ?? [];
            if ((int)$attribute->getIsGlobal() === ScopedAttributeInterface::SCOPE_GLOBAL) {
                $attributeValues = isset($attributeValues[0]) ? [0 => $attributeValues[0]] : [];
                $clearStoreIds = $attributeValues === [] && in_array(0, $clearStoreIds, true) ? [0] : [];
            } elseif ((int)$attribute->getIsGlobal() === ScopedAttributeInterface::SCOPE_WEBSITE) {
                [$attributeValues, $clearStoreIds] = $this->expandWebsiteChanges(
                    $attributeCode,
                    $attributeValues,
                    $clearStoreIds
                );
            }
            $storeIds = array_values(array_unique([
                ...array_map('intval', array_keys($attributeValues)),
                ...array_map('intval', $clearStoreIds),
            ]));
            if ($storeIds === []) {
                continue;
            }
            $backendTable = $this->resourceConnection->getTableName($backendTable);
            $existing = $this->connection()->fetchPairs(
                $this->connection()->select()
                    ->from($backendTable, ['store_id', 'value'])
                    ->where('attribute_id = ?', $attributeId)
                    ->where($linkField . ' = ?', $linkValue)
                    ->where('store_id IN (?)', $storeIds)
            );
            $rows = [];
            foreach ($attributeValues as $storeId => $value) {
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
                $this->connection()->insertOnDuplicate($backendTable, $rows, ['value']);
                $written += count($rows);
            }
            $clearStoreIds = array_values(array_diff(
                array_map('intval', $clearStoreIds),
                array_map('intval', array_keys($attributeValues))
            ));
            $clearStoreIds = array_values(array_intersect(
                $clearStoreIds,
                array_map('intval', array_keys($existing))
            ));
            if ($clearStoreIds !== []) {
                $written += $this->connection()->delete($backendTable, [
                    'attribute_id = ?' => $attributeId,
                    $linkField . ' = ?' => $linkValue,
                    'store_id IN (?)' => $clearStoreIds,
                ]);
            }
        }

        return $written;
    }

    /**
     * @param array<int, float|int|string|string[]> $values
     * @param int[] $clearStoreIds
     * @return array{array<int, float|int|string|string[]>, int[]}
     */
    private function expandWebsiteChanges(string $attributeCode, array $values, array $clearStoreIds): array
    {
        $operations = [];
        $storeIds = array_unique([...array_keys($values), ...$clearStoreIds]);
        foreach ($storeIds as $storeId) {
            if ((int)$storeId === 0) {
                continue;
            }
            $websiteId = (int)$this->storeManager->getStore((int)$storeId)->getWebsiteId();
            $operation = array_key_exists($storeId, $values)
                ? ['value' => $values[$storeId]]
                : ['clear' => true];
            if (isset($operations[$websiteId]) && $operations[$websiteId] !== $operation) {
                throw new LocalizedException(__(
                    'Website-scoped attribute "%1" has conflicting values or clears in website %2.',
                    $attributeCode,
                    $websiteId
                ));
            }
            $operations[$websiteId] = $operation;
        }

        $expandedValues = array_key_exists(0, $values) ? [0 => $values[0]] : [];
        $expandedClear = in_array(0, $clearStoreIds, true) && !array_key_exists(0, $values) ? [0] : [];
        $storesByWebsite = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storesByWebsite[(int)$store->getWebsiteId()][] = (int)$store->getId();
        }
        foreach ($operations as $websiteId => $operation) {
            foreach ($storesByWebsite[(int)$websiteId] ?? [] as $storeId) {
                if (array_key_exists('value', $operation)) {
                    $expandedValues[$storeId] = $operation['value'];
                } else {
                    $expandedClear[] = $storeId;
                }
            }
        }

        return [$expandedValues, $expandedClear];
    }

    private function resolveLinkValue(int $productId, string $linkField): int
    {
        if ($linkField === $this->productResource->getIdFieldName()) {
            return $productId;
        }

        return (int)$this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->productResource->getEntityTable(), [$linkField])
                ->where($this->productResource->getIdFieldName() . ' = ?', $productId)
                ->limit(1)
        );
    }

    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
