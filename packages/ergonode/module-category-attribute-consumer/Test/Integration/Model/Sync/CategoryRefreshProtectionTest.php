<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\Category\Api\CategoryFormContextProviderInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeValueMapper;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeWriter;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryEntityRefresher;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryEntitySynchronizer;
use Ergonode\CategoryConsumer\Api\CategoryEntityLoaderInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameSynchronizerInterface;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\MappedCategoryAttributeSynchronizer;
use Ergonode\Core\Api\MappingVisibilitySaverInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['name' => 'Parent'], as: 'parent')]
#[DataFixture(
    CategoryFixture::class,
    ['name' => 'Child', 'description' => 'Manual', 'parent_id' => '$parent.id$'],
    as: 'child'
)]
class CategoryRefreshProtectionTest extends TestCase
{
    #[DataProvider('cases')]
    public function testRefreshPreservesExcludedCategory(string $excluded): void
    {
        $om = Bootstrap::getObjectManager();
        $child = DataFixtureStorageManager::getStorage()->get('child');
        $id = (int)$child->getId();
        $parentId = (int)DataFixtureStorageManager::getStorage()->get('parent')->getId();
        $rootId = (int)explode('/', $child->getPath())[1];
        $tree = $om->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'reaudit', 'root_category_id' => $rootId, 'is_active' => true, 'remove_missing' => false,
        ]);
        $om->get(CategoryMappingWriter::class)->saveLayout($tree, 'parent', null, 1, $parentId);
        $om->get(CategoryMappingWriter::class)->saveLayout($tree, 'child', 'parent', 1, $id);
        $rows = [];
        foreach (['parent' => null, 'child' => 'parent'] as $code => $parent) {
            $rows[] = ['code' => $code, 'parent_code' => $parent, 'labels' => ['en_US' => $code],
                'sort_order' => 1, 'raw' => ['code' => $code], 'hash' => hash('sha256', $code)];
        }
        $om->get(CategorySnapshotWriter::class)->saveCategories($tree, $rows);
        if (in_array($excluded, ['source', 'source_parent', 'target', 'target_parent'], true)) {
            $source = str_starts_with($excluded, 'source') ? 'ergo' : 'magento';
            $identifier = match ($excluded) {
                'source' => 'child', 'source_parent' => 'parent',
                'target' => (string)$id, 'target_parent' => (string)$parentId,
            };
            $om->get(MappingVisibilitySaverInterface::class)->saveMany([[
                'entity_type' => 'category', 'source' => $source, 'parent_identifier' => (string)$tree,
                'identifier' => $identifier, 'active' => false,
            ]]);
        }
        $resource = $om->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        if (in_array($excluded, ['missing mapping', 'missing snapshot'], true)) {
            $table = $excluded === 'missing mapping' ? 'ergonode_category_mapping' : 'ergonode_category_snapshot';
            $codeColumn = $excluded === 'missing mapping' ? 'ergonode_category_code' : 'category_code';
            $connection->delete($resource->getTableName($table), [
                'category_tree_id = ?' => $tree, $codeColumn . ' = ?' => 'child',
            ]);
        }
        if ($excluded === 'inactive tree') {
            $connection->update($resource->getTableName('ergonode_category_tree'), ['is_active' => 0], [
                'category_tree_id = ?' => $tree,
            ]);
        }
        // Real DB-backed eligibility proves the same pair is protected for stream/backfill.
        $eligible = $om->create(CategoryDataMappingProvider::class)->getValidMappingsByCodes(['child']);
        self::assertSame($excluded === '', isset($eligible['child']));
        $context = $om->get(CategoryFormContextProviderInterface::class)->getForMagentoCategory($id);
        self::assertSame(
            in_array($excluded, ['missing mapping', 'missing snapshot', 'inactive tree'], true) ? null : 'child',
            $context['ergonode_category_code'] ?? null
        );
        $entity = ['code' => 'child', 'labels' => [], 'attributes' => [],
            'hash' => hash('sha256', 'reaudit-child'), 'raw' => []];
        $loader = $this->createStub(CategoryEntityLoaderInterface::class);
        $loader->method('load')->willReturn($entity);
        $mapper = $this->createStub(CategoryAttributeValueMapper::class);
        $mapper->method('mapForSynchronization')->willReturn([
            'values' => ['description' => [0 => 'Imported']], 'clear' => [],
        ]);
        $writer = $om->create(CategoryAttributeWriter::class, ['valueMapper' => $mapper]);
        $names = $this->createStub(CategoryNameSynchronizerInterface::class);
        $names->method('synchronize')->willReturn(0);
        $invalidations = [];
        $cache = $this->createStub(CategoryCacheInvalidator::class);
        $cache->method('invalidateCategories')->willReturnCallback(
            static function (array $ids) use (&$invalidations): void {
                $invalidations[] = $ids;
            }
        );
        $sync = $om->create(CategoryEntitySynchronizer::class, [
            'attributeWriter' => $writer, 'nameSynchronizer' => $names, 'cacheInvalidator' => $cache,
        ]);
        $baseConfig = $this->createStub(CategoryConfigProvider::class);
        $baseConfig->method('isDataSynchronizationEnabled')->willReturn(true);
        $mappedSync = $om->create(MappedCategoryAttributeSynchronizer::class, [
            'configProvider' => $baseConfig, 'entityLoader' => $loader, 'entitySynchronizer' => $sync,
        ]);
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $refresher = $om->create(CategoryEntityRefresher::class, [
            'configProvider' => $config, 'attributeSynchronizer' => $mappedSync,
            'attributePreparation' => $this->createStub(CategoryAttributeSourcePreparation::class),
        ]);
        $failure = null;
        try {
            $refresher->refresh($id);
        } catch (LocalizedException $exception) {
            $failure = $exception;
        }
        self::assertSame(
            $excluded === '' ? 'Imported' : 'Manual',
            $om->get(CategoryRepositoryInterface::class)->get($id, 0)->getDescription()
        );
        self::assertSame($excluded !== '', $failure instanceof LocalizedException);
        self::assertSame($excluded === '' ? [[$id]] : [], $invalidations);
        $snapshotCount = (int)$connection->fetchOne($connection->select()
            ->from($resource->getTableName('ergonode_category_entity_snapshot'), ['COUNT(*)'])
            ->where('category_code = ?', 'child'));
        self::assertSame($excluded === '' ? 1 : 0, $snapshotCount);
    }

    /** @return array<string, array{string}> */
    public static function cases(): array
    {
        return ['active control' => [''], 'excluded source' => ['source'], 'excluded target' => ['target'],
            'excluded source ancestor' => ['source_parent'], 'excluded target ancestor' => ['target_parent'],
            'missing mapping' => ['missing mapping'], 'missing snapshot' => ['missing snapshot'],
            'inactive tree' => ['inactive tree']];
    }
}
