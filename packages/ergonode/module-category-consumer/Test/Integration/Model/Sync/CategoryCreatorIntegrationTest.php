<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Sync;

use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryCreationDataProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryPositionWriterInterface;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationExecutor;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationInputProvider;
use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationService;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreationService;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreator;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\CategoryConsumer\Model\Sync\MappedCategoryAttributeSynchronizer;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['name' => 'Sync creation parent'], as: 'parent')]
#[DataFixture(CategoryFixture::class, ['parent_id' => '$parent.id$'], as: 'existing')]
#[DataFixture(CategoryFixture::class, ['parent_id' => '$existing.id$'], as: 'descendant')]
class CategoryCreatorIntegrationTest extends TestCase
{
    private array $mappingWrites = [];
    private array $progressValues = [];

    public function testCreatesAndReordersSiblingsWithoutChangingTheirPaths(): void
    {
        $fixtures = DataFixtureStorageManager::getStorage();
        $parentId = (int)$fixtures->get('parent')->getId();
        $parentPath = $this->row($parentId)['path'];
        $existingId = (int)$fixtures->get('existing')->getId();
        $descendantId = (int)$fixtures->get('descendant')->getId();
        $existingPath = $this->row($existingId)['path'];
        $descendantPath = $this->row($descendantId)['path'];
        $creator = $this->creator();
        $writer = Bootstrap::getObjectManager()->get(CategoryPositionWriterInterface::class);
        $previousId = 0;
        $created = [];

        for ($index = 0; $index < 12; $index++) {
            $category = $creator->create('Sync sibling ' . $index, $parentId, [
                'is_active' => 1,
                'include_in_menu' => 1,
            ]);
            $expectedPath = $parentPath . '/' . $category['id'];
            self::assertSame($expectedPath, $category['path']);
            self::assertSame($expectedPath, $this->row($category['id'])['path']);
            $writer->move($category['id'], $parentId, $previousId);
            $created[$category['id']] = $expectedPath;
            foreach ($created as $categoryId => $path) {
                self::assertSame($path, $this->row($categoryId)['path']);
            }
            self::assertSame($existingPath, $this->row($existingId)['path']);
            self::assertSame($descendantPath, $this->row($descendantId)['path']);
            $previousId = $category['id'];
        }
    }

    public function testCreatesAChildOfANewCategoryWithItsOwnPathAndLevel(): void
    {
        $parentId = (int)DataFixtureStorageManager::getStorage()->get('parent')->getId();
        $creator = $this->creator();
        $values = ['is_active' => 1, 'include_in_menu' => 0];
        $parent = $creator->create('Sync new parent', $parentId, $values);
        $child = $creator->create('Sync new child', $parent['id'], $values);
        $stored = $this->row($child['id']);

        self::assertSame($this->row($parentId)['path'] . '/' . $parent['id'], $parent['path']);
        self::assertSame($parent['path'] . '/' . $child['id'], $child['path']);
        self::assertSame($child['path'], $stored['path']);
        self::assertSame($parent['id'], (int)$stored['parent_id']);
        self::assertSame($parent['level'] + 1, (int)$stored['level']);
        self::assertSame((int)$stored['level'], $child['level']);
    }

    #[Config('ergonode_category_attributes/synchronization/status', '1')]
    public function testReconcilesThreeLevelsAndRepeatingTheRunMakesNoChanges(): void
    {
        $manager = Bootstrap::getObjectManager();
        $treeId = $manager->get(CategoryTreeRepository::class)->save([
            'is_active' => true,
            'tree_code' => 'sync-creation-tree',
            'root_category_id' => 2,
            'remove_missing' => false,
        ]);
        $sources = [];
        for ($index = 0; $index < 12; $index++) {
            $code = 'sync-branch-' . $index;
            $sources[] = $this->source($code, null, $index);
            for ($childIndex = 0; $childIndex < 2; $childIndex++) {
                $childCode = $code . '-child-' . $childIndex;
                $sources[] = $this->source($childCode, $code, $childIndex);
                $sources[] = $this->source($childCode . '-leaf', $childCode, 0);
            }
        }
        $service = $this->reconciler($treeId, $sources);
        $request = (new CategoryReconciliationRequest())->setCategoryTreeId($treeId)->setMode('apply');
        $first = $service->execute($request);

        self::assertSame([], $first->getConflicts());
        self::assertSame(60, $first->getStats()['created']);
        self::assertSame(0, $first->getStats()['unmatched']);
        self::assertCount(120, $this->mappingWrites);
        self::assertSame([2], array_values(array_unique(array_count_values($this->mappingWrites))));
        $this->assertProgress(60);
        $mappings = $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId);
        self::assertCount(60, $mappings);
        $before = [];
        foreach ($sources as $source) {
            $categoryId = $mappings[$source['code']];
            $parentId = $source['parent_code'] === null ? 2 : $mappings[$source['parent_code']];
            $before[$categoryId] = $this->row($categoryId);
            self::assertSame($parentId, (int)$before[$categoryId]['parent_id']);
            self::assertSame($this->row($parentId)['path'] . '/' . $categoryId, $before[$categoryId]['path']);
        }

        $this->mappingWrites = [];
        $this->progressValues = [];
        $second = $service->execute($request);
        self::assertSame([], $second->getConflicts());
        foreach (['created', 'moved', 'deleted', 'unmatched'] as $stat) {
            self::assertSame(0, $second->getStats()[$stat], $stat);
        }
        $this->assertSameMappings($mappings, $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId));
        self::assertCount(60, $this->mappingWrites);
        $this->assertProgress(60);
        foreach ($before as $categoryId => $row) {
            self::assertSame($row, $this->row($categoryId));
        }

        // Reproduce Disconnect, Save, Sync using existing Magento categories and real mapping persistence.
        $writer = $manager->get(CategoryMappingWriter::class);
        foreach ($sources as $source) {
            $writer->saveLayout($treeId, $source['code'], $source['parent_code'], $source['sort_order'], null);
        }
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::never())->method('load');
        $attributes = $manager->create(MappedCategoryAttributeSynchronizer::class, ['entityLoader' => $loader]);
        self::assertFalse($attributes->hasWork());
        $this->mappingWrites = [];
        $this->progressValues = [];
        $remapped = $this->reconciler($treeId, $sources, $attributes)->execute($request);
        self::assertSame([], $remapped->getConflicts());
        foreach (['created', 'moved', 'deleted', 'unmatched'] as $stat) {
            self::assertSame(0, $remapped->getStats()[$stat], $stat);
        }
        $this->assertSameMappings($mappings, $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId));
        self::assertCount(60, $this->mappingWrites);
        $this->assertProgress(60);
        foreach ($before as $categoryId => $row) {
            self::assertSame($row, $this->row($categoryId));
        }
    }

    public function testMovesAMappedSubtreeBelowANewParentInALaterPass(): void
    {
        $manager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $parentId = (int)$fixtures->get('parent')->getId();
        $existingId = (int)$fixtures->get('existing')->getId();
        $descendantId = (int)$fixtures->get('descendant')->getId();
        $treeId = $manager->get(CategoryTreeRepository::class)->save([
            'is_active' => true, 'tree_code' => 'sync-late-parent', 'root_category_id' => 2, 'remove_missing' => false,
        ]);
        $writer = $manager->get(CategoryMappingWriter::class);
        foreach (['old' => $parentId, 'moving' => $existingId, 'descendant' => $descendantId] as $code => $id) {
            $writer->updateMagentoLink($treeId, $code, $id);
        }
        $sources = [
            $this->source('old', null, 0),
            $this->source('new-parent', null, 1),
            $this->source('moving', 'new-parent', 0),
            $this->source('descendant', 'moving', 0),
        ];
        $result = $this->reconciler($treeId, $sources)->execute(
            (new CategoryReconciliationRequest())->setCategoryTreeId($treeId)->setMode('apply')
        );
        self::assertSame([], $result->getConflicts());
        self::assertSame(1, $result->getStats()['created']);
        self::assertSame(1, $result->getStats()['moved']);
        self::assertSame(
            ['old' => 1, 'new-parent' => 2, 'moving' => 1, 'descendant' => 1],
            array_count_values($this->mappingWrites)
        );
        $mappings = $manager->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId);
        $newParent = $this->row($mappings['new-parent']);
        self::assertSame((int)$newParent['entity_id'], (int)$this->row($existingId)['parent_id']);
        self::assertSame($newParent['path'] . '/' . $existingId, $this->row($existingId)['path']);
        self::assertSame(
            $newParent['path'] . '/' . $existingId . '/' . $descendantId,
            $this->row($descendantId)['path']
        );
        $this->assertProgress(4);
    }

    /** @param array<int, array<string, mixed>> $sources */
    private function reconciler(
        int $treeId,
        array $sources,
        ?MappedCategoryAttributeSynchronizer $attributes = null
    ): CategoryReconciliationService {
        $manager = Bootstrap::getObjectManager();
        $provider = $manager->get(MagentoCategoryProvider::class);
        $input = $this->createStub(CategoryReconciliationInputProvider::class);
        $input->method('get')->willReturnCallback(static function () use ($treeId, $sources, $provider): array {
            $provider->clearCache();

            return [
                'tree' => ['root_category_id' => 2, 'remove_missing' => false],
                'sources' => $sources,
                'magento' => array_map(
                    static fn (array $category): array => $category + ['active' => true],
                    $provider->getCategories(2)
                ),
                'database_mappings' => Bootstrap::getObjectManager()
                    ->get(CategoryMappingQuery::class)->getMappingsByTreeId($treeId),
                'fresh' => [],
            ];
        });
        $creationData = $this->createStub(CategoryCreationDataProviderInterface::class);
        $creationData->method('get')->willReturn([
            'values' => ['is_active' => 1, 'include_in_menu' => 1],
            'entity' => null,
        ]);
        $creation = $manager->create(CategoryCreationService::class, [
            'creationDataProvider' => $creationData,
            'categoryCreator' => $this->creator(),
        ]);
        $writer = $manager->get(CategoryMappingWriter::class);
        $mapping = $this->createStub(CategoryMappingWriter::class);
        $mapping->method('updateMagentoLink')->willReturnCallback(
            function (int $tree, string $code, int $id, string $status, ?string $message) use ($writer): void {
                $this->mappingWrites[] = $code;
                $writer->updateMagentoLink($tree, $code, $id, $status, $message);
            }
        );
        $progress = $this->createStub(CategorySynchronizationProgress::class);
        $progress->method('checkpoint')->willReturnCallback(function (string $stage, int $processed): void {
            $this->progressValues[] = $processed;
        });

        return $manager->create(CategoryReconciliationService::class, [
            'inputProvider' => $input,
            'executor' => $manager->create(CategoryReconciliationExecutor::class, [
                'categoryCreationService' => $creation,
                'categoryMappingWriter' => $mapping,
                'progress' => $progress,
                'attributeSynchronizer' => $attributes ?? $manager->get(MappedCategoryAttributeSynchronizer::class),
            ]),
        ]);
    }

    private function assertProgress(int $total): void
    {
        $previous = 0;
        foreach ($this->progressValues as $processed) {
            self::assertGreaterThanOrEqual($previous, $processed);
            $previous = $processed;
        }
        self::assertSame($total, $previous);
    }

    /**
     * @param array<string, int> $expected
     * @param array<string, int> $actual
     */
    private function assertSameMappings(array $expected, array $actual): void
    {
        // Mapping queries have no ordering contract; compare identities and their types strictly.
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);
    }

    /** @return array<string, mixed> */
    private function source(string $code, ?string $parentCode, int $position): array
    {
        return [
            'code' => $code,
            'label' => $code,
            'parent_code' => $parentCode,
            'sort_order' => $position,
            'active' => true,
        ];
    }

    private function creator(): CategoryCreator
    {
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('requireAdminLanguageCode')->willReturn('en_GB');

        return Bootstrap::getObjectManager()->create(CategoryCreator::class, [
            'languageMappingProvider' => $languages,
        ]);
    }

    /** @return array<string, mixed> */
    private function row(int $categoryId): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();

        return $connection->fetchRow(
            $connection->select()
                ->from($resource->getTableName('catalog_category_entity'))
                ->where('entity_id = ?', $categoryId)
        );
    }
}
