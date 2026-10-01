<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\Core\Api\MappingRowsPersisterInterface;
use Magento\Framework\App\ResourceConnection;

class OptionMappingPersister
{
    private const string TABLE = 'ergonode_product_option_mapping';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MappingRowsPersisterInterface $mappingRowsPersister
    ) {
    }

    /**
     * Replace the complete manual mapping state for one attribute pair.
     *
     * @param  array<string, array<string, mixed>> $normalized
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int}
     */
    public function replace(int $attributeMappingId, array $normalized): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $this->mappingRowsPersister->persist(
            $connection,
            $this->tableName(),
            $this->loadByLogicalKey($attributeMappingId),
            $normalized
        );
    }

    /**
     * @return array<string, array<string, mixed>> Rows keyed by Ergonode option code.
     */
    public function loadByOptionCode(int $attributeMappingId): array
    {
        $result = [];

        foreach ($this->loadRows($attributeMappingId) as $row) {
            $optionCode = trim((string)($row['ergonode_option_code'] ?? ''));
            $key = $optionCode !== '' ? $optionCode : '#mapping:' . (int)$row['mapping_id'];
            $result[$key] = $row;
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>> $existingByOptionCode
     * @return 'inserted'|'updated'|'unchanged'
     */
    public function upsertComplete(
        int $attributeMappingId,
        string $ergonodeOptionCode,
        int $magentoOptionId,
        int $sortOrder,
        array &$existingByOptionCode
    ): string {
        $payload = [
            'attribute_mapping_id' => $attributeMappingId,
            'ergonode_option_code' => $ergonodeOptionCode,
            'magento_option_id' => $magentoOptionId,
            'status' => 'complete',
        ];
        $payload['content_hash'] = $this->hash($payload);
        $payload['sort_order'] = $sortOrder;
        $existing = $existingByOptionCode[$ergonodeOptionCode] ?? null;
        $connection = $this->resourceConnection->getConnection();

        if ($existing === null) {
            $connection->insert($this->tableName(), $payload);
            $existingByOptionCode[$ergonodeOptionCode] = $payload;
            return 'inserted';
        }

        if ((int)($existing['magento_option_id'] ?? 0) === $magentoOptionId
            && (string)($existing['status'] ?? '') === 'complete'
            && (string)($existing['content_hash'] ?? '') === $payload['content_hash']
            && (int)($existing['sort_order'] ?? 0) === $sortOrder
        ) {
            return 'unchanged';
        }

        $connection->update(
            $this->tableName(),
            $payload,
            ['mapping_id = ?' => (int)$existing['mapping_id']]
        );
        $existingByOptionCode[$ergonodeOptionCode] = $payload + [
            'mapping_id' => (int)$existing['mapping_id'],
        ];
        return 'updated';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function hash(array $payload): string
    {
        return $this->mappingRowsPersister->hash($payload);
    }

    public function logicalKey(string $ergonodeOptionCode, ?int $magentoOptionId): string
    {
        if ($ergonodeOptionCode !== '' && $magentoOptionId !== null) {
            return 'full:' . $ergonodeOptionCode . '|' . $magentoOptionId;
        }

        return $ergonodeOptionCode !== ''
            ? 'ergo:' . $ergonodeOptionCode
            : 'magento:' . (string)$magentoOptionId;
    }

    /**
     * @param int[]    $attributeMappingIds
     * @param string[] $ergonodeOptionCodes
     */
    public function deleteByOptionCodes(array $attributeMappingIds, array $ergonodeOptionCodes): int
    {
        $attributeMappingIds = array_values(array_filter(array_map('intval', $attributeMappingIds)));
        $ergonodeOptionCodes = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (mixed $code): string => trim((string)$code),
                        $ergonodeOptionCodes
                    ),
                    static fn (string $code): bool => $code !== ''
                )
            )
        );
        if ($attributeMappingIds === [] || $ergonodeOptionCodes === []) {
            return 0;
        }

        $conditions = [
            'attribute_mapping_id IN (?)' => $attributeMappingIds,
            'ergonode_option_code IN (?)' => $ergonodeOptionCodes,
        ];
        $deleted = $this->resourceConnection->getConnection()->delete($this->tableName(), $conditions);

        return $deleted;
    }

    /**
     * @param int[] $attributeMappingIds
     */
    public function deleteByAttributeMappingIds(array $attributeMappingIds): int
    {
        $attributeMappingIds = array_values(array_filter(array_map('intval', $attributeMappingIds)));
        if ($attributeMappingIds === []) {
            return 0;
        }

        $conditions = ['attribute_mapping_id IN (?)' => $attributeMappingIds];
        $deleted = $this->resourceConnection->getConnection()->delete($this->tableName(), $conditions);

        return $deleted;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadByLogicalKey(int $attributeMappingId): array
    {
        $result = [];

        foreach ($this->loadRows($attributeMappingId) as $row) {
            $leftCode = trim((string)($row['ergonode_option_code'] ?? ''));
            $rightId = isset($row['magento_option_id']) && $row['magento_option_id'] !== null
                ? (int)$row['magento_option_id']
                : null;
            $result[$this->logicalKey($leftCode, $rightId)] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRows(int $attributeMappingId): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $connection->fetchAll(
            $connection->select()
                ->from($this->tableName())
                ->where('attribute_mapping_id = ?', $attributeMappingId)
        );
    }

    private function tableName(): string
    {
        return $this->resourceConnection->getTableName(self::TABLE);
    }
}
