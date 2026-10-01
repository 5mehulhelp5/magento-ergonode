<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Import;

use Ergonode\ProductAttribute\Model\Mapping\OptionMappingPersister;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Throwable;

class OptionSnapshotReconciler
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly OptionMappingPersister $optionMappingPersister
    ) {
    }

    /**
     * Reconcile one product attribute mapping after a complete shared snapshot refresh.
     *
     * @return array{mappings_removed: int, magento_options_deleted: int}
     */
    public function reconcile(int $mappingId, string $sourceCode, string $targetCode): array
    {
        $sourceCode = trim($sourceCode);
        $targetCode = trim($targetCode);
        if ($mappingId <= 0 || $sourceCode === '' || $targetCode === '') {
            return $this->emptyStats();
        }

        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            $targetAttributeId = $this->loadTargetAttributeId($mappingId, $sourceCode, $targetCode);
            if ($targetAttributeId <= 0) {
                $connection->commit();

                return $this->emptyStats();
            }

            $staleRows = $this->loadStaleMappings($mappingId, $sourceCode);
            if ($staleRows === []) {
                $connection->commit();

                return $this->emptyStats();
            }

            $optionCodes = $this->normalizeStrings(array_column($staleRows, 'ergonode_option_code'));
            $optionIds = array_values(array_unique(array_filter(array_map(
                static fn (array $row): int => (int)$row['magento_option_id'],
                $staleRows
            ))));
            $mappingsRemoved = $this->optionMappingPersister->deleteByOptionCodes([$mappingId], $optionCodes);
            $magentoOptionsDeleted = $connection->delete(
                $this->resourceConnection->getTableName('eav_attribute_option'),
                [
                    'option_id IN (?)' => $optionIds,
                    'attribute_id = ?' => $targetAttributeId,
                ]
            );
            $connection->commit();

            return [
                'mappings_removed' => $mappingsRemoved,
                'magento_options_deleted' => $magentoOptionsDeleted,
            ];
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array{ergonode_option_code: string, magento_option_id: int|string}>
     */
    private function loadStaleMappings(int $mappingId, string $sourceCode): array
    {
        $connection = $this->getConnection();
        $currentCodes = array_fill_keys($this->normalizeStrings($connection->fetchCol(
            $connection->select()
                ->from($this->resourceConnection->getTableName('ergonode_attribute_option'), ['option_code'])
                ->where('attribute_code = ?', $sourceCode)
        )), true);
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName('ergonode_product_option_mapping'), [
                    'ergonode_option_code',
                    'magento_option_id',
                ])
                ->where('attribute_mapping_id = ?', $mappingId)
                ->where('status = ?', 'complete')
                ->where('ergonode_option_code IS NOT NULL')
                ->where('magento_option_id IS NOT NULL')
        );

        return array_values(array_filter($rows, static function (array $row) use ($currentCodes): bool {
            $code = trim((string)$row['ergonode_option_code']);

            return $code !== '' && !isset($currentCodes[$code]) && (int)$row['magento_option_id'] > 0;
        }));
    }

    private function loadTargetAttributeId(int $mappingId, string $sourceCode, string $targetCode): int
    {
        $connection = $this->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()
                ->from(['mapping' => $this->resourceConnection->getTableName('ergonode_product_attribute_mapping')], [])
                ->join(
                    ['attribute' => $this->resourceConnection->getTableName('eav_attribute')],
                    'attribute.attribute_code = mapping.magento_attribute_code',
                    ['attribute_id']
                )
                ->join(
                    ['entity_type' => $this->resourceConnection->getTableName('eav_entity_type')],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->where('entity_type.entity_type_code = ?', 'catalog_product')
                ->where('mapping.mapping_id = ?', $mappingId)
                ->where('mapping.status = ?', 'complete')
                ->where('mapping.ergonode_attribute_code = ?', $sourceCode)
                ->where('mapping.magento_attribute_code = ?', $targetCode)
                ->where('attribute.attribute_code = ?', $targetCode)
                ->where('attribute.frontend_input IN (?)', ['select', 'multiselect'])
        );
    }

    /**
     * @param array<int, int|string|null> $values
     * @return string[]
     */
    private function normalizeStrings(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value) && !is_int($value)) {
                continue;
            }

            $value = trim((string)$value);
            if ($value !== '') {
                $normalized[$value] = $value;
            }
        }

        return array_values($normalized);
    }

    /** @return array{mappings_removed: int, magento_options_deleted: int} */
    private function emptyStats(): array
    {
        return ['mappings_removed' => 0, 'magento_options_deleted' => 0];
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
