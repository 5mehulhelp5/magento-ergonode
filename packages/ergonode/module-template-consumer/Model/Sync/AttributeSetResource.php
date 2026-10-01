<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Sync;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\ResourceConnection;

class AttributeSetResource
{
    private ?int $productEntityTypeId = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductResource $productResource
    ) {
    }

    public function getProductEntityTypeId(): int
    {
        if ($this->productEntityTypeId === null) {
            $connection = $this->resourceConnection->getConnection();
            $this->productEntityTypeId = (int)$connection->fetchOne(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('eav_entity_type'), ['entity_type_id'])
                    ->where('entity_type_code = ?', ProductAttributeInterface::ENTITY_TYPE_CODE)
                    ->limit(1)
            );
        }

        return $this->productEntityTypeId;
    }

    public function getDefaultProductAttributeSetId(): int
    {
        return (int)$this->productResource->getEntityType()->getDefaultAttributeSetId();
    }

    public function productAttributeSetExists(int $attributeSetId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $exists = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('eav_attribute_set'), ['attribute_set_id'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->where('entity_type_id = ?', $this->getProductEntityTypeId())
                ->limit(1)
        );

        return (bool)$exists;
    }

    public function findAttributeSetIdByName(string $attributeSetName): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        $id = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('eav_attribute_set'), ['attribute_set_id'])
                ->where('entity_type_id = ?', $this->getProductEntityTypeId())
                ->where('attribute_set_name = ?', $attributeSetName)
                ->limit(1)
        );

        return $id ? (int)$id : null;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getProductAttributeSets(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('eav_attribute_set'),
                    ['attribute_set_id', 'attribute_set_name']
                )
                ->where('entity_type_id = ?', $this->getProductEntityTypeId())
                ->order('attribute_set_name ASC')
        );

        return array_map(
            static fn (array $row): array => [
                'id' => (int)$row['attribute_set_id'],
                'name' => (string)$row['attribute_set_name'],
            ],
            $rows
        );
    }
}
