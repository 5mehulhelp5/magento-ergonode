<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Snapshot;

use Ergonode\AttributeConsumer\Model\ResourceModel\AttributeDefinitionSnapshot;
use Ergonode\Core\Model\Import\CursorStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[AppIsolation(true), DbIsolation(true)]
class AttributeDefinitionReconciliationTest extends TestCase
{
    #[DataFixture(ProductFixture::class, ['description' => 'Preserved description'])]
    public function testReconcilesDefinitionsAndOptionsWhilePreservingMappingAndMagentoData(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert($resource->getTableName('ergonode_attribute'), [
            'code' => 'reconcile_deleted', 'type' => 'text', 'scope' => 'global',
            'labels_json' => '{}', 'parameters_json' => '{}', 'content_hash' => hash('sha256', 'old'),
        ]);
        $connection->insert($resource->getTableName('ergonode_attribute_option'), [
            'attribute_code' => 'reconcile_deleted', 'option_code' => 'old',
            'labels_json' => '{}', 'content_hash' => hash('sha256', 'option'),
        ]);
        $mappingTable = $resource->getTableName('ergonode_product_attribute_mapping');
        $connection->insert($mappingTable, ['ergonode_attribute_code' => 'reconcile_deleted',
            'magento_attribute_code' => 'description', 'ergonode_type' => 'text', 'magento_type' => 'textarea',
            'status' => 'complete', 'content_hash' => hash('sha256', 'mapping')]);
        $mappingId = $connection->lastInsertId($mappingTable);
        $beforeEav = $connection->fetchAll(
            $connection->select()->from($resource->getTableName('catalog_product_entity_text'))
        );
        self::assertNotEmpty($beforeEav);
        $snapshot = $objects->get(AttributeDefinitionSnapshot::class);
        $state = ['source' => 'integration', 'attribute' => 'attribute-next', 'deleted' => 'deleted-next'];
        $removed = $snapshot->replace([], $state);

        self::assertContains('reconcile_deleted', $removed);
        self::assertNotContains('reconcile_deleted', $snapshot->getCodes());
        self::assertSame(0, (int)$connection->fetchOne($connection->select()
            ->from($resource->getTableName('ergonode_attribute_option'), ['COUNT(*)'])
            ->where('attribute_code = ?', 'reconcile_deleted')));
        self::assertSame('complete', $connection->fetchOne($connection->select()
            ->from($mappingTable, ['status'])->where('mapping_id = ?', $mappingId)));
        self::assertSame($beforeEav, $connection->fetchAll($connection->select()
            ->from($resource->getTableName('catalog_product_entity_text'))));
        self::assertSame($state, $snapshot->getState());
    }

    #[DbIsolation(false)]
    public function testCheckpointFailureRollsBackSnapshotRemoval(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert($resource->getTableName('ergonode_attribute'), [
            'code' => 'reconcile_rollback', 'type' => 'text', 'scope' => 'global',
            'labels_json' => '{}', 'parameters_json' => '{}', 'content_hash' => hash('sha256', 'old'),
        ]);
        $cursors = $this->createMock(CursorStorage::class);
        $cursors->method('save')->willThrowException(new RuntimeException('checkpoint failed'));
        $snapshot = $objects->create(AttributeDefinitionSnapshot::class, ['cursors' => $cursors]);
        try {
            try {
                $snapshot->replace([], ['source' => 'integration', 'attribute' => 'next', 'deleted' => 'next']);
                self::fail('Expected checkpoint failure');
            } catch (RuntimeException $exception) {
                self::assertSame('checkpoint failed', $exception->getMessage());
            }
            self::assertContains('reconcile_rollback', $snapshot->getCodes());
        } finally {
            $connection->delete($resource->getTableName('ergonode_attribute'), ['code = ?' => 'reconcile_rollback']);
        }
    }
}
