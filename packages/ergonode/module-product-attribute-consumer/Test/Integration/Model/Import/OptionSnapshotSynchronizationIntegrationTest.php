<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Test\Integration\Model\Import;

use Ergonode\AttributeConsumer\Model\Import\AttributeCacheWriter;
use Ergonode\AttributeConsumer\Api\AttributeCacheRefresherInterface;
use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttributeConsumer\Model\Import\OptionSnapshotReconciler;
use Ergonode\ProductAttributeConsumer\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttributeConsumer\Model\Sync\MagentoOptionSyncer;
use Ergonode\ProductAttributeConsumer\Model\Sync\OptionSynchronizationProcess;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class OptionSnapshotSynchronizationIntegrationTest extends TestCase
{
    private const string ATTRIBUTE_CODE = 'ergonode_option_it';

    #[Config('ergonode_attributes/options/delete_missing_magento_options', '1')]
    #[DataFixture(ProductFixture::class, ['sku' => 'ergonode-option-assigned-%uniqid%'], as: 'product')]
    public function testConfiguredSynchronizationDeletesOnlyMissingMappedTableOption(): void
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        self::assertInstanceOf(Product::class, $product);
        $this->assertSynchronizedStaleOptionCount(0, false, (int)$product->getId());
    }

    #[Config('ergonode_attributes/options/delete_missing_magento_options', '0')]
    public function testDisabledDeletionPreservesExistingMappingAndMagentoOption(): void
    {
        $this->assertSynchronizedStaleOptionCount(1, false);
    }

    #[Config('ergonode_attributes/options/delete_missing_magento_options', '1')]
    public function testCustomSourceDoesNotDeleteMagentoOption(): void
    {
        $this->assertSynchronizedStaleOptionCount(1, true);
    }

    private function assertSynchronizedStaleOptionCount(
        int $expectedStaleRows,
        bool $customSource,
        ?int $assignedProductId = null
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $dataSetup = $objectManager->get(ModuleDataSetupInterface::class);
        $eavSetup = $objectManager->get(EavSetupFactory::class)->create(['setup' => $dataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
            'type' => 'int',
            'input' => 'select',
            'label' => 'Reconcile options',
            'required' => false,
            'user_defined' => true,
        ]);
        $attribute = $eavSetup->getAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        $attributeId = (int)($attribute['attribute_id'] ?? 0);
        if ($assignedProductId !== null) {
            $attributeSetId = (int)$connection->fetchOne(
                $connection->select()
                    ->from($resource->getTableName('catalog_product_entity'), ['attribute_set_id'])
                    ->where('entity_id = ?', $assignedProductId)
            );
            $eavSetup->addAttributeToSet(
                Product::ENTITY,
                $attributeSetId,
                $eavSetup->getDefaultAttributeGroupId(Product::ENTITY, $attributeSetId),
                self::ATTRIBUTE_CODE
            );
        }

        try {
            $redOptionId = $this->createMagentoOption($resource, $attributeId, 1);
            $blueOptionId = $this->createMagentoOption($resource, $attributeId, 2);
            $unmappedOptionId = $this->createMagentoOption($resource, $attributeId, 3);
            $mappingId = $this->createSnapshot($resource, $redOptionId, $blueOptionId);
            if ($assignedProductId !== null) {
                $connection->insert($resource->getTableName('catalog_product_entity_int'), [
                    'attribute_id' => $attributeId,
                    'store_id' => 0,
                    'entity_id' => $assignedProductId,
                    'value' => $blueOptionId,
                ]);
                $productBeforeSync = $objectManager->get(ProductRepositoryInterface::class)
                    ->getById($assignedProductId, false, 0, true);
                self::assertSame('Label 2', $productBeforeSync->getAttributeText(self::ATTRIBUTE_CODE));
            }
            $mapping = [
                'mapping_id' => $mappingId,
                'ergonode_attribute_code' => self::ATTRIBUTE_CODE,
                'magento_attribute_code' => self::ATTRIBUTE_CODE,
                'ergonode_type' => 'select',
                'magento_type' => 'select',
                'magento_has_custom_source' => $customSource,
            ];
            $mappingProvider = $this->createMock(AttributeMappingProvider::class);
            $mappingProvider->method('getMappingRow')->with($mappingId)->willReturn($mapping);
            $compatibility = $this->createStub(AttributeTypeCompatibilityInterface::class);
            $compatibility->method('canMapOptions')->willReturn(true);
            $refresher = $this->createMock(AttributeCacheRefresherInterface::class);
            $refresher->expects(self::once())->method('refreshOptions')
                ->with(self::ATTRIBUTE_CODE)
                ->willReturnCallback(static function () use ($connection, $resource): void {
                    $connection->delete($resource->getTableName('ergonode_attribute_option'), [
                        'attribute_code = ?' => self::ATTRIBUTE_CODE,
                        'option_code = ?' => 'blue',
                    ]);
                });
            $syncer = $this->createStub(MagentoOptionSyncer::class);
            $syncer->method('syncAttributeMapping')->willReturn([
                'created' => 0,
                'linked' => 0,
                'mappings_inserted' => 0,
                'mappings_updated' => 0,
                'labels_updated' => 0,
                'sort_order_updated' => 0,
                'unchanged' => 1,
                'skipped' => 0,
                'errors' => 0,
            ]);
            $lockManager = $this->createStub(LockManagerInterface::class);
            $lockManager->method('lock')->willReturn(true);

            $objectManager->create(OptionSynchronizationProcess::class, [
                'attributeMappingProvider' => $mappingProvider,
                'typeCompatibility' => $compatibility,
                'attributeCacheRefresher' => $refresher,
                'magentoOptionSyncer' => $syncer,
                'lockManager' => $lockManager,
            ])->execute($mappingId);

            self::assertSame($expectedStaleRows, $this->rowCount($resource, 'ergonode_product_option_mapping', [
                'attribute_mapping_id = ?' => $mappingId,
                'ergonode_option_code = ?' => 'blue',
            ]));
            self::assertSame($expectedStaleRows, $this->rowCount($resource, 'eav_attribute_option', [
                'option_id = ?' => $blueOptionId,
            ]));
            self::assertSame(1, $this->rowCount($resource, 'ergonode_product_option_mapping', [
                'attribute_mapping_id = ?' => $mappingId,
                'ergonode_option_code = ?' => 'red',
            ]));
            self::assertSame(1, $this->rowCount($resource, 'eav_attribute_option', [
                'option_id = ?' => $redOptionId,
            ]));
            self::assertSame(1, $this->rowCount($resource, 'eav_attribute_option', [
                'option_id = ?' => $unmappedOptionId,
            ]));
            if ($assignedProductId !== null) {
                self::assertSame(1, $this->rowCount($resource, 'catalog_product_entity_int', [
                    'attribute_id = ?' => $attributeId,
                    'store_id = ?' => 0,
                    'entity_id = ?' => $assignedProductId,
                    'value = ?' => $blueOptionId,
                ]));
                $product = $objectManager->get(ProductRepositoryInterface::class)
                    ->getById($assignedProductId, false, 0, true);
                self::assertFalse($product->getAttributeText(self::ATTRIBUTE_CODE));
            }
        } finally {
            $connection->delete($resource->getTableName('ergonode_attribute_option'), [
                'attribute_code = ?' => self::ATTRIBUTE_CODE,
            ]);
            $connection->delete($resource->getTableName('ergonode_product_attribute_mapping'), [
                'ergonode_attribute_code = ?' => self::ATTRIBUTE_CODE,
            ]);
            $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        }
    }

    public function testRemoteReorderUpdatesCachedPositions(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $writer = $objectManager->get(AttributeCacheWriter::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_attribute_option');

        $writer->saveOptions(self::ATTRIBUTE_CODE, [
            $this->option('red', 1),
            $this->option('blue', 2),
        ]);
        $writer->saveOptions(self::ATTRIBUTE_CODE, [
            $this->option('blue', 1),
            $this->option('red', 2),
        ]);

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['option_code', 'sort_order'])
                ->where('attribute_code = ?', self::ATTRIBUTE_CODE)
                ->order('sort_order ASC')
        );

        self::assertSame(['blue', 'red'], array_column($rows, 'option_code'));
        self::assertSame([1, 2], array_map('intval', array_column($rows, 'sort_order')));
    }

    public function testReconciliationAfterFullRefreshIsIdempotent(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $dataSetup = $objectManager->get(ModuleDataSetupInterface::class);
        $eavSetup = $objectManager->get(EavSetupFactory::class)->create(['setup' => $dataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        $eavSetup->addAttribute(Product::ENTITY, self::ATTRIBUTE_CODE, [
            'type' => 'int',
            'input' => 'select',
            'label' => 'Reconcile options',
            'required' => false,
            'user_defined' => true,
        ]);
        $attribute = $eavSetup->getAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        $attributeId = (int)($attribute['attribute_id'] ?? 0);

        try {
            $redOptionId = $this->createMagentoOption($resource, $attributeId, 1);
            $blueOptionId = $this->createMagentoOption($resource, $attributeId, 2);
            $mappingId = $this->createSnapshot($resource, $redOptionId, $blueOptionId);

            $connection->delete($resource->getTableName('ergonode_attribute_option'), [
                'attribute_code = ?' => self::ATTRIBUTE_CODE,
                'option_code = ?' => 'blue',
            ]);

            $stats = $objectManager->get(OptionSnapshotReconciler::class)->reconcile(
                $mappingId,
                self::ATTRIBUTE_CODE,
                self::ATTRIBUTE_CODE
            );

            self::assertSame([
                'mappings_removed' => 1,
                'magento_options_deleted' => 1,
            ], $stats);
            self::assertSame([
                'mappings_removed' => 0,
                'magento_options_deleted' => 0,
            ], $objectManager->get(OptionSnapshotReconciler::class)->reconcile(
                $mappingId,
                self::ATTRIBUTE_CODE,
                self::ATTRIBUTE_CODE
            ));
            self::assertSame(1, $this->rowCount($resource, 'ergonode_attribute_option', [
                'attribute_code = ?' => self::ATTRIBUTE_CODE,
                'option_code = ?' => 'red',
            ]));
            self::assertSame(0, $this->rowCount($resource, 'ergonode_attribute_option', [
                'attribute_code = ?' => self::ATTRIBUTE_CODE,
                'option_code = ?' => 'blue',
            ]));
            self::assertSame(1, $this->rowCount($resource, 'ergonode_product_option_mapping', [
                'attribute_mapping_id = ?' => $mappingId,
                'ergonode_option_code = ?' => 'red',
            ]));
            self::assertSame(0, $this->rowCount($resource, 'ergonode_product_option_mapping', [
                'attribute_mapping_id = ?' => $mappingId,
                'ergonode_option_code = ?' => 'blue',
            ]));
            self::assertSame(1, $this->rowCount($resource, 'eav_attribute_option', [
                'option_id = ?' => $redOptionId,
            ]));
            self::assertSame(0, $this->rowCount($resource, 'eav_attribute_option', [
                'option_id = ?' => $blueOptionId,
            ]));
        } finally {
            $connection->delete(
                $resource->getTableName('ergonode_attribute_option'),
                ['attribute_code = ?' => self::ATTRIBUTE_CODE]
            );
            $connection->delete(
                $resource->getTableName('ergonode_product_attribute_mapping'),
                ['ergonode_attribute_code = ?' => self::ATTRIBUTE_CODE]
            );
            $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
        }
    }

    /** @return array{code: string, labels: array<string, string>, sort_order: int, hash: string} */
    private function option(string $code, int $sortOrder): array
    {
        return [
            'code' => $code,
            'labels' => ['en_US' => ucfirst($code)],
            'sort_order' => $sortOrder,
            'hash' => hash('sha256', $code . ':' . $sortOrder),
        ];
    }

    private function createMagentoOption(ResourceConnection $resource, int $attributeId, int $sortOrder): int
    {
        $table = $resource->getTableName('eav_attribute_option');
        $connection = $resource->getConnection();
        self::assertInstanceOf(Mysql::class, $connection);
        $connection->insert($table, [
            'attribute_id' => $attributeId,
            'sort_order' => $sortOrder,
        ]);

        $optionId = (int)$connection->lastInsertId($table);
        $connection->insert($resource->getTableName('eav_attribute_option_value'), [
            'option_id' => $optionId,
            'store_id' => 0,
            'value' => 'Label ' . $sortOrder,
        ]);

        return $optionId;
    }

    private function createSnapshot(ResourceConnection $resource, int $redOptionId, int $blueOptionId): int
    {
        $connection = $resource->getConnection();
        self::assertInstanceOf(Mysql::class, $connection);
        $mappingTable = $resource->getTableName('ergonode_product_attribute_mapping');
        $connection->insert($mappingTable, [
            'ergonode_attribute_code' => self::ATTRIBUTE_CODE,
            'magento_attribute_code' => self::ATTRIBUTE_CODE,
            'ergonode_type' => 'select',
            'magento_type' => 'select',
            'status' => 'complete',
            'content_hash' => hash('sha256', self::ATTRIBUTE_CODE),
        ]);
        $mappingId = (int)$connection->lastInsertId($mappingTable);

        foreach (['red' => $redOptionId, 'blue' => $blueOptionId] as $code => $optionId) {
            $connection->insert($resource->getTableName('ergonode_attribute_option'), [
                'attribute_code' => self::ATTRIBUTE_CODE,
                'option_code' => $code,
                'sort_order' => $code === 'red' ? 1 : 2,
                'labels_json' => sprintf('{"en_US":"%s"}', ucfirst($code)),
                'content_hash' => hash('sha256', $code),
            ]);
            $connection->insert($resource->getTableName('ergonode_product_option_mapping'), [
                'attribute_mapping_id' => $mappingId,
                'ergonode_option_code' => $code,
                'magento_option_id' => $optionId,
                'status' => 'complete',
                'content_hash' => hash('sha256', self::ATTRIBUTE_CODE . ':' . $code),
                'sort_order' => $code === 'red' ? 1 : 2,
            ]);
        }

        return $mappingId;
    }

    /** @param array<string, int|string> $where */
    private function rowCount(ResourceConnection $resource, string $table, array $where): int
    {
        $select = $resource->getConnection()->select()
            ->from($resource->getTableName($table), ['COUNT(*)']);
        foreach ($where as $condition => $value) {
            $select->where($condition, $value);
        }

        return (int)$resource->getConnection()->fetchOne($select);
    }
}
