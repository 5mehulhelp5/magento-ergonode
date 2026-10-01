<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Sync;

use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\Import\CategoryStreamPageReader;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryEntitySynchronizer;
use Ergonode\CategoryConsumer\Model\Sync\CategoryNameSynchronizer;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationAction;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['name' => 'Protected parent', 'parent_id' => 2], as: 'parent')]
#[DataFixture(CategoryFixture::class, ['name' => 'Protected child', 'parent_id' => '$parent.id$'], as: 'child')]
#[DataFixture(CategoryFixture::class, ['name' => 'Active control', 'parent_id' => 2], as: 'visible')]
class CategoryDataExclusionIntegrationTest extends TestCase
{
    public function testNumericCodesPassRealMappingAndVisibilityReadsIntoDataImporter(): void
    {
        $objects = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $ids = ['0' => (int)$fixtures->get('parent')->getId(),
            '123' => (int)$fixtures->get('child')->getId(),
            '001' => (int)$fixtures->get('visible')->getId()];
        $treeId = $objects->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'numeric-data', 'root_category_id' => 2, 'is_active' => true,
        ]);
        $resource = $objects->get(ResourceConnection::class);
        foreach ($ids as $code => $id) {
            $code = (string)$code;
            $resource->getConnection()->insert($resource->getTableName('ergonode_category_snapshot'), [
                'category_tree_id' => $treeId, 'category_code' => $code,
                'parent_category_code' => $code === '123' ? '0' : null,
                'labels_json' => '{}', 'raw_json' => '{}', 'content_hash' => hash('sha256', $code),
            ]);
            $objects->get(CategoryMappingWriter::class)->updateMagentoLink($treeId, $code, $id);
        }
        $query = $objects->get(CategoryMappingQuery::class);
        $expected = [];
        foreach ($ids as $code => $id) {
            $expected[$code] = [['category_tree_id' => $treeId, 'magento_category_id' => $id]];
        }
        $actual = $query->getValidMappingsByCodes(['0', '123', '001']);
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);
        self::assertSame($expected, $objects->create(CategoryDataMappingProvider::class)
            ->getValidMappingsByCodes(['0', '123', '001']));

        $objects->get(MappingVisibilitySaverInterface::class)->saveMany([[
            'entity_type' => 'category', 'source' => 'ergo', 'parent_identifier' => (string)$treeId,
            'identifier' => '0', 'active' => false,
        ]]);
        $visibility = $objects->get(MappingVisibilityProviderInterface::class);
        self::assertSame(
            ['0' => false, 123 => true, '001' => true],
            $visibility->getActiveMap('category', 'ergo', ['0', '123', '001'], (string)$treeId)
        );
        self::assertSame(['001' => $expected['001']], $objects->create(CategoryDataMappingProvider::class)
            ->getValidMappingsByCodes(['0', '123', '001']));

        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => ['0', '123', '001'], 'cursor' => 'numeric-after']);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::once())->method('loadMany')->with(['001'])->willReturn([
            '001' => ['code' => '001', 'labels' => ['en_GB' => 'Updated control'],
                'attributes' => [], 'raw' => [], 'hash' => hash('sha256', '001')],
        ]);
        $language = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $language->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);
        $target = $this->createStub(CategoryNameTargetProviderInterface::class);
        $target->method('getAttributeCode')->willReturn('name');
        $names = $objects->create(CategoryNameSynchronizer::class, [
            'targetProvider' => $target, 'languageMappingProvider' => $language,
        ]);
        $cursor = $objects->get(CursorStorage::class);
        $cursor->save('category_stream', 'numeric-before');
        $stats = (new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $objects->create(CategoryDataMappingProvider::class),
            $loader,
            $this->synchronizer($names, $target),
            $language,
            $this->createStub(CategorySynchronizationProgress::class),
            $this->createStub(CategorySourceAvailability::class)
        ))->execute();
        self::assertSame(1, $stats['fetched']);
        self::assertSame('numeric-after', $cursor->get('category_stream')['cursor']);
        $categoryResource = $objects->get(CategoryResource::class);
        self::assertSame('Protected parent', $categoryResource->getAttributeRawValue($ids['0'], 'name', 0));
        self::assertSame('Protected child', $categoryResource->getAttributeRawValue($ids['123'], 'name', 0));
        self::assertSame('Updated control', $categoryResource->getAttributeRawValue($ids['001'], 'name', 0));
    }

    /** @return array<string, array{bool, bool}> */
    public static function cases(): array
    {
        return ['source/data' => [false, false], 'target/data' => [true, false],
            'source/all' => [false, true], 'target/all' => [true, true]];
    }

    #[DataProvider('cases')]
    public function testProtectedNamesSurviveDataWritesAndCursorsAdvance(bool $targetExcluded, bool $managed): void
    {
        $objects = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $parent = (int)$fixtures->get('parent')->getId();
        $child = (int)$fixtures->get('child')->getId();
        $visible = (int)$fixtures->get('visible')->getId();
        $ids = ['A' => $targetExcluded ? $child : $parent, 'B' => $targetExcluded ? $parent : $child,
            'visible' => $visible];
        $treeId = $objects->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'data-exclusion', 'root_category_id' => 2, 'is_active' => true,
        ]);
        $resource = $objects->get(ResourceConnection::class);
        foreach ($ids as $code => $id) {
            $resource->getConnection()->insert($resource->getTableName('ergonode_category_snapshot'), [
                'category_tree_id' => $treeId, 'category_code' => $code,
                'parent_category_code' => $targetExcluded && $code === 'B' ? 'A' : null,
                'labels_json' => '{}', 'raw_json' => '{}', 'content_hash' => hash('sha256', $code),
            ]);
            $objects->get(CategoryMappingWriter::class)->updateMagentoLink($treeId, $code, $id);
        }
        $objects->get(MappingVisibilitySaverInterface::class)->saveMany([[
            'entity_type' => 'category', 'source' => $targetExcluded ? 'magento' : 'ergo',
            'parent_identifier' => (string)$treeId, 'identifier' => $targetExcluded ? (string)$child : 'A',
            'active' => false,
        ]]);
        $stateProvider = $objects->get(CategoryTreeStateProviderInterface::class);
        $before = $stateProvider->getState($treeId);
        $language = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $language->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);
        $target = $this->createStub(CategoryNameTargetProviderInterface::class);
        $target->method('getAttributeCode')->willReturn('name');
        $names = $objects->create(CategoryNameSynchronizer::class, [
            'targetProvider' => $target, 'languageMappingProvider' => $language,
        ]);
        $synchronizer = $this->synchronizer($names, $target);
        $reader = $this->createStub(CategoryStreamPageReader::class);
        $reader->method('readAll')->willReturn(['codes' => array_keys($ids), 'cursor' => 'data-after']);
        $loader = $this->createMock(CategoryEntityLoaderInterface::class);
        $loader->expects(self::once())->method('loadMany')->with(['visible'])->willReturn([
            'visible' => ['code' => 'visible', 'labels' => ['en_GB' => 'Updated control'],
                'attributes' => [], 'raw' => [], 'hash' => hash('sha256', 'visible')],
        ]);
        $progress = $this->createStub(CategorySynchronizationProgress::class);
        $progress->method('isManaged')->willReturn($managed);
        $cursor = $objects->get(CursorStorage::class);
        $cursor->save('category_stream', 'data-before');
        $cursor->save('category_tree_stream', 'tree-before');
        // Remote attribute registry preparation is outside this data-write contract.
        $importer = new CategoryEntityStreamImporter(
            $reader,
            $cursor,
            $objects->create(CategoryDataMappingProvider::class),
            $loader,
            $synchronizer,
            $language,
            $progress,
            $this->createStub(CategorySourceAvailability::class)
        );
        if ($managed) {
            $treeProcess = $this->createStub(CategoryStructureSynchronizationProcessInterface::class);
            $treeProcess->method('execute')->willReturn(['events' => 0, 'conflicts' => 0, 'cursor' => 'tree-after']);
            $dataProcess = $this->createMock(CategoryDataSynchronizationProcessInterface::class);
            $dataProcess->expects(self::once())->method('execute')->willReturnCallback($importer->execute(...));
            $config = $this->createStub(CategoryConfigProvider::class);
            $config->method('isDataSynchronizationEnabled')->willReturn(true);
            $result = $objects->create(CategorySynchronizationAction::class, [
                'treeProcess' => $treeProcess, 'dataProcess' => $dataProcess,
                'configProvider' => $config, 'progress' => $progress,
            ])->execute('all', 'sync')['results']['data'];
        } else {
            $result = $importer->execute();
        }
        self::assertSame(1, $result['fetched']);
        self::assertSame('data-after', $cursor->get('category_stream')['cursor']);
        self::assertSame($managed ? 'tree-after' : 'tree-before', $cursor->get('category_tree_stream')['cursor']);
        $categoryResource = $objects->get(CategoryResource::class);
        self::assertSame('Protected parent', $categoryResource->getAttributeRawValue($parent, 'name', 0));
        self::assertSame('Protected child', $categoryResource->getAttributeRawValue($child, 'name', 0));
        self::assertSame('Updated control', $categoryResource->getAttributeRawValue($visible, 'name', 0));
        $after = $stateProvider->getState($treeId);
        self::assertSame(array_column($before['source'], 'active'), array_column($after['source'], 'active'));
        self::assertSame(array_column($before['target'], 'active'), array_column($after['target'], 'active'));
    }

    protected function synchronizer(
        CategoryNameSynchronizer $names,
        CategoryNameTargetProviderInterface $target
    ): CategoryEntitySynchronizerInterface {
        return Bootstrap::getObjectManager()->create(CategoryEntitySynchronizer::class, [
            'nameSynchronizer' => $names, 'nameTarget' => $target,
        ]);
    }
}
