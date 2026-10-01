<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Integration\Model\Mapping;

use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Category\Model\Mapping\LockedCategoryLayoutSaver;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingDataPreparer;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingDataWriter;
use Ergonode\CategoryConsumer\Model\Mapping\CategoryMappingSaveHandler;
use Ergonode\CategoryConsumer\Model\Sync\CategoryEntitySynchronizer;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Ergonode\CategoryConsumer\Model\Sync\CategoryNameSynchronizer;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true), Config('ergonode_categories/history/enabled', 1)]
#[DataFixture(CategoryFixture::class, ['name' => 'Parent', 'parent_id' => 2], as: 'parent')]
#[DataFixture(CategoryFixture::class, [
    'name' => 'Manual protected name', 'description' => 'Preserved', 'parent_id' => '$parent.id$',
], as: 'protected')]
#[DataFixture(CategoryFixture::class, ['name' => 'Old mapped target', 'parent_id' => 2], as: 'old')]
#[DataFixture(CategoryFixture::class, ['name' => 'Old control name', 'parent_id' => 2], as: 'control')]
class ManualMappingProtectionTest extends TestCase
{
    public static function cases(): array
    {
        return ['source/persisted' => ['ergo', false], 'target/persisted' => ['magento', false],
            'source/same-save' => ['ergo', true], 'target/same-save' => ['magento', true],
            'source/ancestor/divergent' => ['ergo', true, true],
            'target/ancestor' => ['magento', true, true],
            'inactive-tree' => ['ergo', true, false, false, false],
            'remapping' => ['magento', true, false, true],
            'numeric-codes' => ['ergo', true, false, false, true, true]];
    }

    #[DataProvider('cases')]
    public function testManualMappingPreservesExcludedName(
        string $side,
        bool $sameSave,
        bool $ancestor = false,
        bool $remap = false,
        bool $active = true,
        bool $numericCodes = false
    ): void {
        $objects = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $ids = ['protected' => (int)$fixtures->get('protected')->getId(),
            'control' => (int)$fixtures->get('control')->getId()];
        $tree = $objects->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'manual-mapping-audit', 'root_category_id' => 2, 'is_active' => $active,
        ]);
        $resource = $objects->get(ResourceConnection::class);
        $codes = $numericCodes
            ? ['protected' => '0', 'control' => '123', 'parent' => '001']
            : ['protected' => 'protected', 'control' => 'control', 'parent' => 'parent'];
        foreach (['protected', 'control', 'parent'] as $sort => $alias) {
            $code = $codes[$alias];
            $resource->getConnection()->insert($resource->getTableName('ergonode_category_snapshot'), [
                'category_tree_id' => $tree, 'category_code' => $code, 'sort_order' => $sort,
                'labels_json' => '{"en_GB":"Source"}', 'raw_json' => '{}', 'content_hash' => hash('sha256', $code),
            ]);
        }
        $mappingWriter = $objects->get(CategoryMappingWriter::class);
        $mappingWriter->saveLayout($tree, $codes['parent'], null, 2, (int)$fixtures->get('parent')->getId());
        if ($remap) {
            $mappingWriter->saveLayout($tree, $codes['protected'], null, 0, (int)$fixtures->get('old')->getId());
        }
        $excludedCode = $codes[$ancestor ? 'parent' : 'protected'];
        $visibility = [
            'source' => $side,
            'identifier' => $side === 'ergo' ? $excludedCode
                : (string)$fixtures->get($ancestor ? 'parent' : 'protected')->getId(),
            'active' => false];
        if (!$sameSave) {
            $objects->get(MappingVisibilitySaverInterface::class)->saveMany([
                $visibility + ['entity_type' => 'category', 'parent_identifier' => (string)$tree],
            ]);
        }
        $language = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $language->method('getLanguageStoreMap')->willReturn([0 => 'en_GB']);
        $target = $this->createStub(CategoryNameTargetProviderInterface::class);
        $target->method('getAttributeCode')->willReturn('name');
        $names = $objects->create(CategoryNameSynchronizer::class, [
            'targetProvider' => $target, 'languageMappingProvider' => $language,
        ]);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('clean')->with(['cat_c', 'cat_c_' . $ids['control']]);
        $types = $this->createMock(TypeListInterface::class);
        $types->expects(self::exactly(2))->method('cleanType');
        $invalidator = $objects->create(CategoryCacheInvalidator::class, ['cache' => $cache, 'typeList' => $types]);
        $synchronizer = $this->synchronizer($names, $target, $invalidator);
        $loader = $this->createStub(CategoryEntityLoaderInterface::class);
        $loader->method('loadMany')->willReturn([
            $codes['protected'] => ['code' => $codes['protected'], 'labels' => ['en_GB' => 'Imported protected name'],
                'attributes' => [], 'raw' => [], 'hash' => hash('sha256', 'protected')],
            $codes['control'] => ['code' => $codes['control'], 'labels' => ['en_GB' => 'Imported control name'],
                'attributes' => [], 'raw' => [], 'hash' => hash('sha256', 'control')],
        ]);
        $config = $this->createStub(CategoryConfigProvider::class);
        $config->method('isDataSynchronizationEnabled')->willReturn(true);
        $preparer = new CategoryMappingDataPreparer($loader, $synchronizer, $config);
        $writer = $objects->create(CategoryMappingDataWriter::class, [
            'synchronizer' => $synchronizer, 'cacheInvalidator' => $invalidator,
        ]);
        $handler = new CategoryMappingSaveHandler($preparer, $writer);
        $saver = $objects->create(CategoryLayoutSaver::class, ['dataWriter' => $handler]);
        $locked = $objects->create(LockedCategoryLayoutSaver::class, ['categoryLayoutSaver' => $saver]);
        $cursor = $objects->get(CursorStorage::class);
        $cursor->save('category_stream', 'data-before');
        $cursor->save('category_tree_stream', 'tree-before');
        $stats = $locked->save($tree, [
            ['code' => $codes['protected'], 'parent_code' => null, 'sort_order' => 0,
                'magento_category_id' => $ids['protected']],
            ['code' => $codes['control'], 'parent_code' => null, 'sort_order' => 1,
                'magento_category_id' => $ids['control']],
            ['code' => $codes['parent'], 'parent_code' => null, 'sort_order' => 2,
                'magento_category_id' => (int)$fixtures->get('parent')->getId()],
        ], $sameSave ? [$visibility] : []);
        $categoryResource = $objects->get(CategoryResource::class);
        $actual = $categoryResource->getAttributeRawValue($ids['protected'], 'name', 0);
        $control = $categoryResource->getAttributeRawValue($ids['control'], 'name', 0);
        $state = $objects->get(CategoryTreeStateProviderInterface::class)->getState($tree);
        $rows = array_column($state[$side === 'ergo' ? 'source' : 'target'], null, 'identifier');
        self::assertFalse($rows[$visibility['identifier']]['active']);
        self::assertSame('Imported control name', $control);
        self::assertSame(2, $stats['updated']);
        $sources = array_column($state['source'], null, 'identifier');
        self::assertSame($ids['protected'], $sources[$codes['protected']]['magento_category_id']);
        self::assertSame('data-before', $cursor->get('category_stream')['cursor']);
        self::assertSame('tree-before', $cursor->get('category_tree_stream')['cursor']);
        self::assertSame('Old mapped target', $categoryResource->getAttributeRawValue(
            (int)$fixtures->get('old')->getId(),
            'name',
            0
        ));
        self::assertSame('Manual protected name', $actual, 'Excluded category must preserve its name.');
    }

    protected function synchronizer(
        CategoryNameSynchronizer $names,
        CategoryNameTargetProviderInterface $target,
        CategoryCacheInvalidator $invalidator
    ): CategoryEntitySynchronizerInterface {
        return Bootstrap::getObjectManager()->create(CategoryEntitySynchronizer::class, [
            'nameSynchronizer' => $names, 'nameTarget' => $target, 'cacheInvalidator' => $invalidator,
        ]);
    }
}
