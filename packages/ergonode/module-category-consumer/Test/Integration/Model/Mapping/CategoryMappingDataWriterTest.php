<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Mapping;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameWriterInterface;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingDataWriter;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\ResourceModel\CategoryWriteTransaction;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[AppIsolation(true), DbIsolation(false)]
#[DataFixture(CategoryFixture::class, ['name' => 'Atomic mapping'], as: 'category')]
class CategoryMappingDataWriterTest extends TestCase
{
    public function testFailedValueWriteRollsBackBothMappingAndCategoryName(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        self::assertSame(0, $connection->getTransactionLevel());
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category')->getId();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, ['tree_code' => 'atomic-mapping-test', 'root_category_id' => 2]);
        $treeId = (int)$connection->lastInsertId($treeTable);
        $snapshotTable = $resource->getTableName('ergonode_category_snapshot');
        $connection->insert($snapshotTable, [
            'category_tree_id' => $treeId, 'category_code' => 'chairs', 'labels_json' => '{}',
            'raw_json' => '{}', 'content_hash' => hash('sha256', 'chairs'),
        ]);
        $visibility = $objects->get(MappingVisibilitySaverInterface::class);
        $mappingTable = $resource->getTableName('ergonode_category_mapping');
        $mappingWriter = $objects->get(CategoryMappingWriter::class);
        $nameWriter = $objects->get(CategoryNameWriterInterface::class);
        $invalidator = $this->createStub(CategoryCacheInvalidator::class);
        $invalidator->method('defer')->willReturnCallback(static fn (callable $operation): mixed => $operation());
        $synchronizer = $this->createMock(CategoryEntitySynchronizerInterface::class);
        $synchronizer->expects(self::once())->method('synchronize')->willReturnCallback(
            static function (array $operations) use ($nameWriter, $categoryId): array {
                self::assertSame([['category_id' => $categoryId, 'entity' => ['code' => 'chairs']]], $operations);
                self::assertSame(1, $nameWriter->write($categoryId, 'name', [0 => 'Changed before failure']));
                throw new RuntimeException('Injected value failure');
            }
        );
        $writer = new CategoryMappingDataWriter(
            new CategoryWriteTransaction($resource),
            $synchronizer,
            $invalidator,
            $objects->get(CategoryDataMappingProvider::class)
        );
        try {
            try {
                $saveLayout = static function () use ($mappingWriter, $treeId, $categoryId, $visibility): array {
                    $mappingWriter->saveLayout($treeId, 'chairs', null, 0, $categoryId);
                    $visibility->saveMany([[
                        'entity_type' => 'category', 'source' => 'ergo', 'parent_identifier' => (string)$treeId,
                        'identifier' => 'other', 'active' => false,
                    ]]);
                    return ['updated' => 1, 'unchanged' => 0, 'attribute_values' => 0];
                };
                $writer->save($treeId, $saveLayout, [['category_id' => $categoryId, 'entity' => ['code' => 'chairs']]]);
                self::fail('Expected write failure.');
            } catch (RuntimeException $error) {
                self::assertSame('Injected value failure', $error->getMessage());
            }
            self::assertSame(0, (int)$connection->fetchOne($connection->select()
                ->from($mappingTable, ['COUNT(*)'])->where('category_tree_id = ?', $treeId)));
            $categoryResource = $objects->get(CategoryResource::class);
            self::assertSame('Atomic mapping', $categoryResource->getAttributeRawValue($categoryId, 'name', 0));
            self::assertNotSame(['other' => false], $objects->get(MappingVisibilityProviderInterface::class)
                ->getActiveMap('category', 'ergo', ['other'], (string)$treeId));
            self::assertSame(0, $connection->getTransactionLevel());
        } finally {
            $connection->delete($mappingTable, ['category_tree_id = ?' => $treeId]);
            $connection->delete($snapshotTable, ['category_tree_id = ?' => $treeId]);
            $connection->delete($treeTable, ['category_tree_id = ?' => $treeId]);
        }
    }
}
