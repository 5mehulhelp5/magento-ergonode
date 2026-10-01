<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Snapshot;

use Ergonode\AttributeConsumer\Api\AttributeSnapshotRemoverInterface;
use Ergonode\AttributeConsumer\Api\OptionSnapshotRemoverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class AttributeSnapshotRemoverIntegrationTest extends TestCase
{
    public function testAttributeRemovalPreservesAttributeAndOptionMappings(): void
    {
        [$resource, $mappingId] = $this->createSnapshot('snapshot_attribute');
        $connection = $resource->getConnection();

        Bootstrap::getObjectManager()->get(AttributeSnapshotRemoverInterface::class)
            ->remove('snapshot_attribute');

        self::assertSame(0, $this->countRows($resource, 'ergonode_attribute', 'code', 'snapshot_attribute'));
        self::assertSame(
            0,
            $this->countRows(
                $resource,
                'ergonode_attribute_option',
                'attribute_code',
                'snapshot_attribute'
            )
        );
        self::assertSame(1, (int)$connection->fetchOne(
            $connection->select()->from(
                $resource->getTableName('ergonode_product_attribute_mapping'),
                ['COUNT(*)']
            )->where('mapping_id = ?', $mappingId)
        ));
        self::assertSame(1, (int)$connection->fetchOne(
            $connection->select()->from(
                $resource->getTableName('ergonode_product_option_mapping'),
                ['COUNT(*)']
            )->where('attribute_mapping_id = ?', $mappingId)
        ));
    }

    public function testOptionRemovalPreservesOptionMapping(): void
    {
        [$resource, $mappingId] = $this->createSnapshot('snapshot_option_attribute');
        $connection = $resource->getConnection();

        Bootstrap::getObjectManager()->get(OptionSnapshotRemoverInterface::class)
            ->remove('snapshot_option_attribute', 'snapshot_option');

        self::assertSame(
            0,
            $this->countRows(
                $resource,
                'ergonode_attribute_option',
                'attribute_code',
                'snapshot_option_attribute'
            )
        );
        self::assertSame(1, (int)$connection->fetchOne(
            $connection->select()->from(
                $resource->getTableName('ergonode_product_option_mapping'),
                ['COUNT(*)']
            )->where('attribute_mapping_id = ?', $mappingId)
                ->where('ergonode_option_code = ?', 'snapshot_option')
        ));
    }

    /** @return array{ResourceConnection, int} */
    private function createSnapshot(string $attributeCode): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $attributeTable = $resource->getTableName('ergonode_attribute');
        $mappingTable = $resource->getTableName('ergonode_product_attribute_mapping');
        $connection->insert($attributeTable, [
            'code' => $attributeCode,
            'type' => 'select',
            'scope' => 'global',
            'labels_json' => '{"en_US":"Snapshot attribute"}',
            'parameters_json' => '{}',
            'content_hash' => hash('sha256', $attributeCode),
        ]);
        $connection->insert($resource->getTableName('ergonode_attribute_option'), [
            'attribute_code' => $attributeCode,
            'option_code' => 'snapshot_option',
            'labels_json' => '{"en_US":"Snapshot option"}',
            'content_hash' => hash('sha256', $attributeCode . ':option'),
        ]);
        $connection->insert($mappingTable, [
            'ergonode_attribute_code' => $attributeCode,
            'magento_attribute_code' => null,
            'ergonode_type' => 'select',
            'magento_type' => null,
            'status' => 'partial',
            'content_hash' => hash('sha256', $attributeCode . ':mapping'),
        ]);
        $mappingId = (int)$connection->lastInsertId($mappingTable);
        $connection->insert($resource->getTableName('ergonode_product_option_mapping'), [
            'attribute_mapping_id' => $mappingId,
            'ergonode_option_code' => 'snapshot_option',
            'magento_option_id' => null,
            'status' => 'partial',
            'content_hash' => hash('sha256', $attributeCode . ':option-mapping'),
        ]);

        return [$resource, $mappingId];
    }

    private function countRows(
        ResourceConnection $resource,
        string $table,
        string $column,
        string $value
    ): int {
        $connection = $resource->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()->from($resource->getTableName($table), ['COUNT(*)'])
                ->where($column . ' = ?', $value)
        );
    }
}
