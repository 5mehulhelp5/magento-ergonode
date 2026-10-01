<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Sync;

use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Import\CategoryTreePageReader;
use Ergonode\Category\Model\Import\CategoryTreeDownloader;
use Ergonode\Category\Model\Import\FreshCategoryTreeLoader;
use Ergonode\Category\Model\Import\MissingCategoryTreeException;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationInputProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationService;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['name' => 'Preserved source category', 'parent_id' => 2], as: 'category')]
class CategorySourceProtectionIntegrationTest extends TestCase
{
    public function testEmptySourceNeverDeletesMagentoOrMappingWithLegacyRemovalEnabled(): void
    {
        $manager = Bootstrap::getObjectManager();
        [$treeId, $categoryId] = $this->mapping();
        $provider = $manager->get(MagentoCategoryProvider::class);
        $input = $this->createStub(CategoryReconciliationInputProvider::class);
        $input->method('get')->willReturn([
            'tree' => ['category_tree_id' => $treeId, 'root_category_id' => 2, 'remove_missing' => true],
            'sources' => [], 'magento' => $provider->getCategories(2),
            'database_mappings' => ['removed' => $categoryId], 'fresh' => ['complete' => true],
        ]);
        $service = $manager->create(CategoryReconciliationService::class, ['inputProvider' => $input]);

        $result = $service->execute(
            (new CategoryReconciliationRequest())->setCategoryTreeId($treeId)->setMode('apply')
        );

        self::assertSame(0, $result->getStats()['deleted']);
        self::assertSame(
            $categoryId,
            (int)$manager->get(CategoryRepositoryInterface::class)->get($categoryId)->getId()
        );
        self::assertSame(
            ['removed' => $categoryId],
            $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId)
        );
    }

    public function testPreviewAndApplyPreserveExcludedBranchAcrossUnmappedSourceNodes(): void
    {
        $manager = Bootstrap::getObjectManager();
        [$treeId, $categoryId] = $this->mapping();
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('catalog_category_entity');
        $catalogBefore = $connection->fetchAll($connection->select()->from($table)->order('entity_id'));
        $provider = $manager->get(MagentoCategoryProvider::class);
        $magento = $provider->getCategories(2);
        $magento[$categoryId]['active'] = false;
        $input = $this->createStub(CategoryReconciliationInputProvider::class);
        $input->method('get')->willReturn([
            'tree' => ['category_tree_id' => $treeId, 'root_category_id' => 2, 'is_active' => true],
            'sources' => [
                ['code' => 'removed', 'parent_code' => null, 'label' => 'Parent', 'sort_order' => 0],
                ['code' => 'gap', 'parent_code' => 'removed', 'label' => 'Gap', 'sort_order' => 0],
                ['code' => 'leaf', 'parent_code' => 'gap', 'label' => 'Leaf', 'sort_order' => 0],
            ],
            'magento' => $magento, 'database_mappings' => ['removed' => $categoryId], 'fresh' => [],
        ]);
        $service = $manager->create(CategoryReconciliationService::class, ['inputProvider' => $input]);
        foreach (['preview', 'apply'] as $mode) {
            $result = $service->execute(
                (new CategoryReconciliationRequest())->setCategoryTreeId($treeId)->setMode($mode)
            );
            self::assertSame([], $result->getConflicts());
            self::assertSame(['excluded', 'excluded', 'excluded'], array_column(
                $result->getCategories(),
                'mapping_source'
            ));
            self::assertSame(0, $result->getStats()['created']);
            self::assertSame(0, $result->getStats()['moved']);
            self::assertSame(
                ['removed' => $categoryId],
                $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId)
            );
            self::assertSame(
                $catalogBefore,
                $connection->fetchAll($connection->select()->from($table)->order('entity_id'))
            );
        }
    }

    public function testUnmappedSourceExclusionPreservesMappedDescendantOnPreviewAndApply(): void
    {
        $manager = Bootstrap::getObjectManager();
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category')->getId();
        $treeId = $manager->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'unmapped-exclusion', 'root_category_id' => 2, 'is_active' => true,
        ]);
        $manager->get(CategoryMappingWriter::class)->saveLayout($treeId, 'child', 'parent', null, $categoryId);
        $resource = $manager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName('catalog_category_entity');
        $catalogBefore = $connection->fetchAll($connection->select()->from($table)->order('entity_id'));
        $input = $this->createStub(CategoryReconciliationInputProvider::class);
        $input->method('get')->willReturn([
            'tree' => ['category_tree_id' => $treeId, 'root_category_id' => 2, 'is_active' => true],
            'sources' => [
                ['code' => 'parent', 'parent_code' => null, 'label' => 'Excluded', 'active' => false],
                ['code' => 'child', 'parent_code' => 'parent', 'label' => 'Mapped', 'active' => true],
                ['code' => 'leaf', 'parent_code' => 'child', 'label' => 'Nested', 'active' => false],
            ],
            'magento' => $manager->get(MagentoCategoryProvider::class)->getCategories(2),
            'database_mappings' => ['child' => $categoryId], 'fresh' => [],
        ]);
        $service = $manager->create(CategoryReconciliationService::class, ['inputProvider' => $input]);
        foreach (['preview', 'apply'] as $mode) {
            $result = $service->execute(
                (new CategoryReconciliationRequest())->setCategoryTreeId($treeId)->setMode($mode)
            );
            self::assertSame([], $result->getConflicts());
            self::assertSame(
                ['excluded', 'excluded', 'excluded'],
                array_column($result->getCategories(), 'mapping_source')
            );
            self::assertSame(0, $result->getStats()['created']);
            self::assertSame(0, $result->getStats()['moved']);
            self::assertSame(
                ['child' => $categoryId],
                $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId)
            );
            self::assertSame(
                $catalogBefore,
                $connection->fetchAll($connection->select()->from($table)->order('entity_id'))
            );
        }
    }

    public function testMissingTreePreservesSnapshotAndPersistsAttentionAcrossInstances(): void
    {
        $manager = Bootstrap::getObjectManager();
        [$treeId, $categoryId] = $this->mapping();
        $reader = $this->createStub(CategoryTreePageReader::class);
        $reader->method('read')->willReturnOnConsecutiveCalls(
            ['seconds' => 0.1, 'data' => ['categoryTree' => [
                'code' => 'source-protection',
                'categoryTreeLeafList' => [
                    'edges' => [['node' => ['category' => ['code' => 'removed', 'name' => []]]]],
                    'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                ],
            ]]],
            ['seconds' => 0.1, 'data' => ['categoryTree' => null]]
        );
        $loader = $manager->create(FreshCategoryTreeLoader::class, [
            'downloader' => $manager->create(CategoryTreeDownloader::class, ['pageReader' => $reader]),
        ]);
        $loader->load($treeId);
        $resource = $manager->get(ResourceConnection::class);
        $query = $resource->getConnection()->select()
            ->from($resource->getTableName('ergonode_category_snapshot'))
            ->where('category_tree_id = ?', $treeId);
        $before = $resource->getConnection()->fetchAll($query);
        self::assertCount(1, $before);
        try {
            $loader->load($treeId);
            self::fail('Missing tree must stop the download.');
        } catch (MissingCategoryTreeException) {
            self::assertSame($before, $resource->getConnection()->fetchAll($query));
        }
        $state = $manager->create(CategoryTreeSourceState::class)->get($treeId);
        self::assertSame('missing', $state['status']);
        self::assertTrue($state['requires_refresh']);
        self::assertNotNull($state['snapshot_at']);
        self::assertSame(
            ['removed' => $categoryId],
            $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId)
        );
    }

    /** @return array{int, int} */
    private function mapping(): array
    {
        $manager = Bootstrap::getObjectManager();
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category')->getId();
        $treeId = $manager->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'source-protection', 'root_category_id' => 2,
            'is_active' => true, 'remove_missing' => true,
        ]);
        $manager->get(CategoryMappingWriter::class)->saveLayout($treeId, 'removed', null, null, $categoryId);

        return [$treeId, $categoryId];
    }
}
