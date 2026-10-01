<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Sync;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql as PdoMysql;

class TemplateStructureResource
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function beginTransaction(): void
    {
        $this->getConnection()->beginTransaction();
    }

    public function commit(): void
    {
        $this->getConnection()->commit();
    }

    public function rollBack(): void
    {
        $this->getConnection()->rollBack();
    }

    /**
     * @param string[] $attributeCodes
     * @return array<string, array{attribute_id: int, is_user_defined: bool}>
     */
    public function loadMagentoAttributes(int $entityTypeId, array $attributeCodes): array
    {
        $attributeCodes = array_values(array_unique(array_filter($attributeCodes)));
        if ($attributeCodes === []) {
            return [];
        }

        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName('eav_attribute'),
                    ['attribute_code', 'attribute_id', 'is_user_defined']
                )
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('attribute_code IN (?)', $attributeCodes)
        );

        $attributes = [];
        foreach ($rows as $row) {
            $attributes[(string)$row['attribute_code']] = [
                'attribute_id' => (int)$row['attribute_id'],
                'is_user_defined' => (bool)$row['is_user_defined'],
            ];
        }

        return $attributes;
    }

    /**
     * @param int[] $attributeIds
     * @return array<int, array{entity_attribute_id: int, attribute_group_id: int, sort_order: int}>
     */
    public function loadEntityAttributeRows(int $attributeSetId, array $attributeIds): array
    {
        $attributeIds = array_values(array_unique(array_map('intval', $attributeIds)));
        if ($attributeIds === []) {
            return [];
        }

        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName('eav_entity_attribute'),
                    ['entity_attribute_id', 'attribute_id', 'attribute_group_id', 'sort_order']
                )
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_id IN (?)', $attributeIds)
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['attribute_id']] = [
                'entity_attribute_id' => (int)$row['entity_attribute_id'],
                'attribute_group_id' => (int)$row['attribute_group_id'],
                'sort_order' => (int)$row['sort_order'],
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAttributeGroupById(int $attributeSetId, int $attributeGroupId): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('eav_attribute_group'))
                ->where('attribute_group_id = ?', $attributeGroupId)
                ->where('attribute_set_id = ?', $attributeSetId)
                ->limit(1)
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAttributeGroupByCode(int $attributeSetId, string $groupCode): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('eav_attribute_group'))
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_group_code = ?', $groupCode)
                ->limit(1)
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDefaultAttributeGroup(int $attributeSetId): ?array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('eav_attribute_group'))
                ->where('attribute_set_id = ?', $attributeSetId)
                ->order('default_id DESC')
                ->order('sort_order ASC')
                ->order('attribute_group_id ASC')
                ->limit(1)
        );

        return is_array($row) ? $row : null;
    }

    public function getNextAttributeSortOrder(int $attributeSetId, int $attributeGroupId): int
    {
        $maximum = (int)$this->getConnection()->fetchOne(
            $this->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName('eav_entity_attribute'),
                    ['maximum_sort_order' => 'MAX(sort_order)']
                )
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_group_id = ?', $attributeGroupId)
        );

        return $maximum > 0 ? $maximum + 10 : 10;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertAttributeGroup(array $data): int
    {
        /** @var PdoMysql $connection */
        $connection = $this->getConnection();
        $connection->insert($this->resourceConnection->getTableName('eav_attribute_group'), $data);

        return (int)$connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAttributeGroup(int $attributeGroupId, array $data): void
    {
        $this->getConnection()->update(
            $this->resourceConnection->getTableName('eav_attribute_group'),
            $data,
            ['attribute_group_id = ?' => $attributeGroupId]
        );
    }

    public function insertEntityAttribute(
        int $entityTypeId,
        int $attributeSetId,
        int $attributeGroupId,
        int $attributeId,
        int $sortOrder
    ): int {
        /** @var PdoMysql $connection */
        $connection = $this->getConnection();
        $connection->insert($this->resourceConnection->getTableName('eav_entity_attribute'), [
            'entity_type_id' => $entityTypeId,
            'attribute_set_id' => $attributeSetId,
            'attribute_group_id' => $attributeGroupId,
            'attribute_id' => $attributeId,
            'sort_order' => $sortOrder,
        ]);

        return (int)$connection->lastInsertId();
    }

    /**
     * @param array<string, int> $data
     */
    public function updateEntityAttribute(int $entityAttributeId, array $data): void
    {
        $this->getConnection()->update(
            $this->resourceConnection->getTableName('eav_entity_attribute'),
            $data,
            ['entity_attribute_id = ?' => $entityAttributeId]
        );
    }

    public function deleteEntityAttribute(int $entityAttributeId): int
    {
        return $this->getConnection()->delete(
            $this->resourceConnection->getTableName('eav_entity_attribute'),
            ['entity_attribute_id = ?' => $entityAttributeId]
        );
    }

    public function entityAttributeExists(int $entityAttributeId): bool
    {
        return (bool)$this->getConnection()->fetchOne(
            $this->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('eav_entity_attribute'), ['COUNT(*)'])
                ->where('entity_attribute_id = ?', $entityAttributeId)
        );
    }

    public function countGroupAttributes(int $attributeSetId, int $attributeGroupId): int
    {
        return (int)$this->getConnection()->fetchOne(
            $this->getConnection()
                ->select()
                ->from($this->resourceConnection->getTableName('eav_entity_attribute'), ['COUNT(*)'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('attribute_group_id = ?', $attributeGroupId)
        );
    }

    public function deleteAttributeGroup(int $attributeGroupId): void
    {
        $this->getConnection()->delete(
            $this->resourceConnection->getTableName('eav_attribute_group'),
            ['attribute_group_id = ?' => $attributeGroupId]
        );
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
