<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttribute\Model\ResourceModel;

use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Magento\Framework\App\ResourceConnection;

class MappingReader implements MappingReaderInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getAttributeRows(): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $connection->fetchAll($connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_attribute_mapping'))
            ->order('sort_order ASC')->order('mapping_id ASC'));
    }

    public function getAttributeRow(int $mappingId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow($connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_attribute_mapping'))
            ->where('mapping_id = ?', $mappingId)->limit(1));

        return is_array($row) ? $row : null;
    }

    public function getOptionRows(int $attributeMappingId): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $connection->fetchAll($connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_option_mapping'))
            ->where('attribute_mapping_id = ?', $attributeMappingId)
            ->order('sort_order ASC')->order('mapping_id ASC'));
    }

    public function getCompleteOptionRows(array $attributeMappingIds): array
    {
        if ($attributeMappingIds === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();

        return $connection->fetchAll($connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_option_mapping'))
            ->where('attribute_mapping_id IN (?)', $attributeMappingIds)
            ->where('ergonode_option_code IS NOT NULL')->where('magento_option_id IS NOT NULL')
            ->where('status = ?', 'complete'));
    }

    public function getCompleteOptionCounts(array $attributeMappingIds): array
    {
        if ($attributeMappingIds === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();

        return array_map('intval', $connection->fetchPairs($connection->select()
            ->from($this->resourceConnection->getTableName('ergonode_category_option_mapping'), [
                'attribute_mapping_id', 'count' => 'COUNT(*)',
            ])->where('attribute_mapping_id IN (?)', $attributeMappingIds)
            ->where('status = ?', 'complete')->group('attribute_mapping_id')));
    }
}
