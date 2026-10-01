<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Storage;

use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;

use Ergonode\Category\Api\CategorySnapshotRemoverInterface;
use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Provider\CategoryRemoteIdentityProvider;
use Ergonode\CategoryAttributeConsumer\Model\Snapshot\CategoryEntitySnapshotWriter;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeWriter;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryMappedAttributeBackfiller;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Ergonode\CategoryAttributeConsumer\Model\ResourceModel\CategoryBackfillSnapshotReader;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;

use function array_replace;

#[AppIsolation(true), DbIsolation(true)]
class CategoryStorageSeparationIntegrationTest extends TestCase
{
    public function testOutboundCategoryMappingRequiresActiveTreeSnapshotMembership(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, $this->categoryTreeRow('outbound-active-tree', 942));
        $activeTreeId = (int)$connection->lastInsertId($treeTable);
        $connection->insert(
            $treeTable,
            array_replace($this->categoryTreeRow('outbound-inactive-tree', 943), ['is_active' => 0])
        );
        $inactiveTreeId = (int)$connection->lastInsertId($treeTable);
        $connection->insertMultiple($resource->getTableName('ergonode_category_mapping'), [
            [
                'category_tree_id' => $activeTreeId,
                'ergonode_category_code' => 'visible-category',
                'magento_category_id' => 201,
            ],
            [
                'category_tree_id' => $activeTreeId,
                'ergonode_category_code' => 'stale-category',
                'magento_category_id' => 202,
            ],
            [
                'category_tree_id' => $inactiveTreeId,
                'ergonode_category_code' => 'inactive-category',
                'magento_category_id' => 203,
            ],
        ]);
        $snapshotWriter = $objectManager->get(CategorySnapshotWriter::class);
        $snapshotWriter->saveCategories($activeTreeId, [$this->category('Visible', 'visible-category')]);
        $snapshotWriter->saveCategories($inactiveTreeId, [$this->category('Inactive', 'inactive-category')]);

        self::assertSame(
            [201 => 'visible-category'],
            $objectManager->get(CategoryMappingProviderInterface::class)
                ->getCategoryCodesByMagentoIds([201, 202, 203])
        );
    }

    public function testCategoryMappingProviderPreservesFanoutAndRestrictsDefaultCategoryToActiveRoot(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, $this->categoryTreeRow('reference-tree', 940));
        $activeTreeId = (int)$connection->lastInsertId($treeTable);
        $connection->insert(
            $treeTable,
            array_replace($this->categoryTreeRow('inactive-reference-tree', 941), ['is_active' => 0])
        );
        $inactiveTreeId = (int)$connection->lastInsertId($treeTable);
        $connection->insertMultiple($resource->getTableName('ergonode_category_mapping'), [
            [
                'category_tree_id' => $activeTreeId,
                'ergonode_category_code' => 'chairs',
                'magento_category_id' => 201,
                'sync_status' => 'complete',
            ],
            [
                'category_tree_id' => $activeTreeId,
                'ergonode_category_code' => 'pending',
                'magento_category_id' => 202,
                'sync_status' => 'pending',
            ],
            [
                'category_tree_id' => $inactiveTreeId,
                'ergonode_category_code' => 'chairs',
                'magento_category_id' => 301,
                'sync_status' => 'complete',
            ],
        ]);
        $provider = $objectManager->get(CategoryMappingProviderInterface::class);

        self::assertSame(
            ['chairs' => [201, 301]],
            $provider->getMagentoCategoryIdsByErgonodeCodes(['chairs', 'pending'])
        );
        self::assertSame(
            ['chairs' => [201]],
            $provider->getMagentoCategoryIdsByErgonodeCodes(['chairs'], 940)
        );
        self::assertSame([], $provider->getMagentoCategoryIdsByErgonodeCodes(['chairs'], 941));
    }

    public function testStreamSelectionRequiresOnlyActiveTreeMapping(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('ergonode_category_tree');
        $connection->insertMultiple($table, [
            $this->categoryTreeRow('stream-tree', 921),
            $this->categoryTreeRow('stream-tree', 922),
            array_replace(
                $this->categoryTreeRow('stream-tree', 923),
                ['is_active' => 0]
            ),
        ]);

        $trees = $objectManager->get(CategoryTreeQuery::class)->getSynchronizableByTreeCodes(['stream-tree']);

        self::assertSame([921, 922], array_column($trees, 'root_category_id'));
    }

    public function testEntityStreamsAffectMappingsBelongingToActiveTreesOnly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, $this->categoryTreeRow('active-tree', 924));
        $activeTreeId = (int)$connection->lastInsertId($treeTable);
        $connection->insert(
            $treeTable,
            array_replace($this->categoryTreeRow('inactive-tree', 925), ['is_active' => 0])
        );
        $inactiveTreeId = (int)$connection->lastInsertId($treeTable);
        $mappingTable = $resource->getTableName('ergonode_category_mapping');
        $connection->insertMultiple($mappingTable, [
            [
                'category_tree_id' => $activeTreeId,
                'ergonode_category_code' => 'shared-category',
                'magento_category_id' => 101,
            ],
            [
                'category_tree_id' => $inactiveTreeId,
                'ergonode_category_code' => 'shared-category',
                'magento_category_id' => 102,
            ],
        ]);

        self::assertSame([
            'shared-category' => [[
                'category_tree_id' => $activeTreeId,
                'magento_category_id' => 101,
            ]],
        ], $objectManager->get(CategoryMappingQuery::class)->getValidMappingsByCodes(['shared-category']));
        self::assertSame(
            ['shared-category' => 101],
            $objectManager->get(CategoryMappingQuery::class)->getMappingsByTreeId($activeTreeId)
        );
    }

    public function testAttributeBackfillUsesOnlyCodesPresentInCurrentTreeSnapshot(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, $this->categoryTreeRow('backfill-tree', 926));
        $categoryTreeId = (int)$connection->lastInsertId($treeTable);
        $connection->insertMultiple($resource->getTableName('ergonode_category_mapping'), [
            [
                'category_tree_id' => $categoryTreeId,
                'ergonode_category_code' => 'current-category',
                'magento_category_id' => 101,
            ],
            [
                'category_tree_id' => $categoryTreeId,
                'ergonode_category_code' => 'stale-category',
                'magento_category_id' => 102,
            ],
        ]);
        $objectManager->get(CategorySnapshotWriter::class)->saveCategories(
            $categoryTreeId,
            [$this->category('Current', 'current-category')]
        );
        $entitySnapshotWriter = $objectManager->get(CategoryEntitySnapshotWriter::class);
        $entitySnapshotWriter->save($this->entitySnapshot('current-category', 'current-value'));
        $entitySnapshotWriter->save($this->entitySnapshot('stale-category', 'stale-value'));

        $attributeWriter = $this->createMock(CategoryAttributeWriter::class);
        $attributeWriter->expects(self::once())
            ->method('writeMappedValues')
            ->with(101, $this->entitySnapshot('current-category', 'current-value')['attributes'])
            ->willReturn(2);
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $mappings = $this->createStub(CategoryDataMappingProvider::class);
        $mappings->method('getValidMappingsByCodes')->willReturn([
            'current-category' => [['category_tree_id' => $categoryTreeId, 'magento_category_id' => 101]],
            'stale-category' => [['category_tree_id' => $categoryTreeId, 'magento_category_id' => 102]],
        ]);
        $stats = (new CategoryMappedAttributeBackfiller(
            $this->createMock(CategoryAttributeSourcePreparation::class),
            $objectManager->get(CategoryBackfillSnapshotReader::class),
            $objectManager->get(Json::class),
            $attributeWriter,
            $this->createStub(LoggerInterface::class),
            $config,
            $mappings,
            $this->createStub(CategoryCacheInvalidator::class)
        ))->execute();

        self::assertSame(['categories' => 1, 'values' => 2, 'errors' => 0], $stats);
    }

    public function testSnapshotRefreshAndCleanupPreserveManualMapping(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $categoryTreeId = $objectManager->get(CategoryTreeRepository::class)->save([
            'is_active' => true,
            'tree_code' => 'tree',
            'root_category_id' => $this->rootCategoryId(
                $objectManager->get(StoreManagerInterface::class)
            ),
            'remove_missing' => true,
        ]);
        $snapshotWriter = $objectManager->get(CategorySnapshotWriter::class);
        $mappingWriter = $objectManager->get(CategoryMappingWriter::class);

        $snapshotWriter->markAllUnseen($categoryTreeId);
        $snapshotWriter->saveCategories($categoryTreeId, [$this->category('first')]);
        $mappingWriter->saveLayout($categoryTreeId, 'category-a', 'parent-manual', 7, null);
        $snapshotWriter->saveCategories($categoryTreeId, [$this->category('changed')]);

        $connection = $objectManager->get(ResourceConnection::class);
        $adapter = $connection->getConnection();
        $mappingTable = $connection->getTableName('ergonode_category_mapping');
        self::assertSame(
            'parent-manual',
            $adapter->fetchOne(
                $adapter->select()->from($mappingTable, ['manual_parent_code'])
                    ->where('category_tree_id = ?', $categoryTreeId)
                    ->where('ergonode_category_code = ?', 'category-a')
            )
        );

        $snapshotWriter->markAllUnseen($categoryTreeId);
        $snapshotWriter->saveCategories($categoryTreeId, [$this->category('other', 'other-category')]);
        self::assertSame(1, $snapshotWriter->deleteUnseen($categoryTreeId));
        self::assertSame(
            'parent-manual',
            $adapter->fetchOne(
                $adapter->select()->from($mappingTable, ['manual_parent_code'])
                    ->where('category_tree_id = ?', $categoryTreeId)
                    ->where('ergonode_category_code = ?', 'category-a')
            )
        );
    }

    public function testJoinedRowsAreStrictlyIsolatedByCategoryTreeId(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $categoryTreeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insertMultiple($categoryTreeTable, [
            $this->categoryTreeRow('category-tree-one', 901),
            $this->categoryTreeRow('category-tree-two', 902),
        ]);
        $categoryTreeIds = array_map(
            'intval',
            $connection->fetchPairs(
                $connection->select()->from($categoryTreeTable, ['tree_code', 'category_tree_id'])
                    ->where('tree_code IN (?)', ['category-tree-one', 'category-tree-two'])
            )
        );

        $snapshotWriter = $objectManager->get(CategorySnapshotWriter::class);
        $mappingWriter = $objectManager->get(CategoryMappingWriter::class);
        foreach ($categoryTreeIds as $code => $categoryTreeId) {
            $snapshotWriter->saveCategories($categoryTreeId, [$this->category($code)]);
            $mappingWriter->saveLayout($categoryTreeId, 'category-a', $code, null, null);
        }

        $provider = $objectManager->get(CategoryCacheProvider::class);
        self::assertSame(
            'category-tree-one',
            $provider->getRowsByCode($categoryTreeIds['category-tree-one'])['category-a']['manual_parent_code']
        );
        self::assertSame(
            'category-tree-two',
            $provider->getRowsByCode($categoryTreeIds['category-tree-two'])['category-a']['manual_parent_code']
        );
    }

    public function testRemoteIdentityCheckpointIsAvailableToSubsequentBatches(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $categoryTreeId = $objectManager->get(CategoryTreeRepository::class)->save([
            'is_active' => true,
            'tree_code' => 'remote-identity-tree',
            'root_category_id' => $this->rootCategoryId($objectManager->get(StoreManagerInterface::class)),
            'remove_missing' => false,
        ]);
        $objectManager->get(CategorySnapshotWriter::class)->saveCategories(
            $categoryTreeId,
            [$this->category('Chairs', 'chairs')]
        );
        $objectManager->get(CategoryMappingWriter::class)->saveRemoteIdentities($categoryTreeId, [[
            'code' => 'chairs',
            'remote_id' => 'f8446fd2-e2ed-46bb-a497-9e5319d798fe',
            'manual_parent_code' => null,
            'manual_sort_order' => 1,
            'magento_category_id' => null,
        ]]);

        self::assertSame(
            ['chairs' => 'f8446fd2-e2ed-46bb-a497-9e5319d798fe'],
            $objectManager->get(CategoryRemoteIdentityProvider::class)->getIdsByCode($categoryTreeId, ['chairs'])
        );
        self::assertSame(
            'f8446fd2-e2ed-46bb-a497-9e5319d798fe',
            $objectManager->get(CategoryCacheProvider::class)
                ->getRowsByCode($categoryTreeId)['chairs']['ergonode_category_id']
        );
    }

    public function testCompleteEmptySnapshotRemovesOnlySelectedTree(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $categoryTreeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insertMultiple($categoryTreeTable, [
            $this->categoryTreeRow('empty-source-tree', 911),
            $this->categoryTreeRow('preserved-source-tree', 912),
        ]);
        $categoryTreeIds = array_map(
            'intval',
            $connection->fetchPairs(
                $connection->select()->from($categoryTreeTable, ['tree_code', 'category_tree_id'])
                    ->where('tree_code IN (?)', ['empty-source-tree', 'preserved-source-tree'])
            )
        );
        $snapshotWriter = $objectManager->get(CategorySnapshotWriter::class);
        foreach ($categoryTreeIds as $categoryTreeId) {
            $snapshotWriter->replaceCompleteSnapshot($categoryTreeId, [$this->category('existing')]);
        }

        $result = $snapshotWriter->replaceCompleteSnapshot($categoryTreeIds['empty-source-tree'], []);
        $snapshotTable = $resource->getTableName('ergonode_category_snapshot');

        self::assertSame(1, $result['removed']);
        self::assertSame(0, (int)$connection->fetchOne(
            $connection->select()->from($snapshotTable, ['COUNT(*)'])
                ->where('category_tree_id = ?', $categoryTreeIds['empty-source-tree'])
        ));
        self::assertSame(1, (int)$connection->fetchOne(
            $connection->select()->from($snapshotTable, ['COUNT(*)'])
                ->where('category_tree_id = ?', $categoryTreeIds['preserved-source-tree'])
        ));
    }

    public function testManualSnapshotRemovalPreservesMagentoMappingAndOtherTreeSnapshot(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $categoryTreeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insertMultiple($categoryTreeTable, [
            $this->categoryTreeRow('snapshot-removal-tree', 941),
            $this->categoryTreeRow('snapshot-preserved-tree', 942),
        ]);
        $categoryTreeIds = array_map(
            'intval',
            $connection->fetchPairs(
                $connection->select()->from($categoryTreeTable, ['tree_code', 'category_tree_id'])
                    ->where('tree_code IN (?)', ['snapshot-removal-tree', 'snapshot-preserved-tree'])
            )
        );
        $snapshotWriter = $objectManager->get(CategorySnapshotWriter::class);
        foreach ($categoryTreeIds as $categoryTreeId) {
            $snapshotWriter->saveCategories($categoryTreeId, [$this->category('Chairs', 'chairs')]);
        }
        $objectManager->get(CategoryMappingWriter::class)->saveLayout(
            $categoryTreeIds['snapshot-removal-tree'],
            'chairs',
            null,
            0,
            42
        );

        $objectManager->get(CategorySnapshotRemoverInterface::class)->remove(
            $categoryTreeIds['snapshot-removal-tree'],
            'chairs'
        );

        $snapshotTable = $resource->getTableName('ergonode_category_snapshot');
        self::assertSame(0, (int)$connection->fetchOne(
            $connection->select()->from($snapshotTable, ['COUNT(*)'])
                ->where('category_tree_id = ?', $categoryTreeIds['snapshot-removal-tree'])
                ->where('category_code = ?', 'chairs')
        ));
        self::assertSame(1, (int)$connection->fetchOne(
            $connection->select()->from($snapshotTable, ['COUNT(*)'])
                ->where('category_tree_id = ?', $categoryTreeIds['snapshot-preserved-tree'])
                ->where('category_code = ?', 'chairs')
        ));
        self::assertSame('42', (string)$connection->fetchOne(
            $connection->select()->from(
                $resource->getTableName('ergonode_category_mapping'),
                ['magento_category_id']
            )->where('category_tree_id = ?', $categoryTreeIds['snapshot-removal-tree'])
                ->where('ergonode_category_code = ?', 'chairs')
        ));
    }

    /**
     * @return array{
     *     code: string,
     *     parent_code: string|null,
     *     labels: array<string, string>,
     *     sort_order: int,
     *     raw: array<string, mixed>,
     *     hash: string
     * }
     */
    private function category(string $label, string $code = 'category-a'): array
    {
        return [
            'code' => $code,
            'parent_code' => null,
            'labels' => ['en_US' => $label],
            'sort_order' => 1,
            'raw' => ['label' => $label],
            'hash' => hash('sha256', $label),
        ];
    }

    /**
     * @return array{
     *     code: string,
     *     labels: array<string, string>,
     *     attributes: array<int, array{code: string, type: string, values: array<string, string>}>,
     *     hash: string,
     *     raw: array<string, string>
     * }
     */
    private function entitySnapshot(string $code, string $value): array
    {
        return [
            'code' => $code,
            'labels' => ['en_US' => $code],
            'attributes' => [[
                'code' => 'description',
                'type' => 'text',
                'values' => ['en_US' => $value],
            ]],
            'hash' => hash('sha256', $code . $value),
            'raw' => ['code' => $code],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryTreeRow(string $code, int $rootId): array
    {
        return [
            'is_active' => 1,
            'tree_code' => $code,
            'root_category_id' => $rootId,
            'remove_missing' => 0,
        ];
    }

    private function rootCategoryId(StoreManagerInterface $storeManager): int
    {
        foreach ($storeManager->getGroups() as $group) {
            $rootId = (int)$group->getRootCategoryId();
            if ($rootId > 0) {
                return $rootId;
            }
        }

        self::fail('Magento integration fixture has no store group root.');
    }
}
