<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model;

use Magento\Framework\App\ResourceConnection;

class ManualPlacementResource
{
    private const string TABLE = 'ergonode_template_manual_placement';

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /** @return int[] */
    public function getAttributeIds(int $attributeSetId): array
    {
        $connection = $this->resourceConnection->getConnection();

        return array_map('intval', $connection->fetchCol($connection->select()
            ->from($this->resourceConnection->getTableName(self::TABLE), ['attribute_id'])
            ->where('attribute_set_id = ?', $attributeSetId)));
    }

    public function isManual(int $attributeSetId, int $attributeId): bool
    {
        $connection = $this->resourceConnection->getConnection();

        return (bool)$connection->fetchOne($connection->select()
            ->from($this->resourceConnection->getTableName(self::TABLE), ['attribute_id'])
            ->where('attribute_set_id = ?', $attributeSetId)->where('attribute_id = ?', $attributeId));
    }

    public function save(int $attributeSetId, int $attributeId, bool $manual): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        if ($manual) {
            $connection->insertOnDuplicate($table, [
                'attribute_set_id' => $attributeSetId,
                'attribute_id' => $attributeId,
            ], ['attribute_id']);
        } else {
            $connection->delete(
                $table,
                ['attribute_set_id = ?' => $attributeSetId, 'attribute_id = ?' => $attributeId]
            );
        }
    }
}
