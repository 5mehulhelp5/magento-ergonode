<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Test\Integration\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeSourcePreparation;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeValueMapper;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryAttributeWriter;
use Ergonode\CategoryAttributeConsumer\Model\Sync\CategoryMappedAttributeBackfiller;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Cache;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[AppIsolation(true), DbIsolation(true)]
#[DataFixture(CategoryFixture::class, ['name' => 'Manually maintained'], as: 'category')]
class CategoryBackfillProtectionTest extends TestCase
{
    #[DataProvider('excludedNodes')]
    public function testPreservesExcludedValuesAndInvalidatesOnlyWrites(string $excluded): void
    {
        $objects = Bootstrap::getObjectManager();
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category')->getId();
        $this->seed($categoryId);
        $state = $this->createStub(CategoryTreeStateProviderInterface::class);
        $state->method('getState')->willReturn([
            'tree' => ['is_active' => $excluded !== 'tree', 'root_category_id' => 2],
            'source' => [
                ['identifier' => 'chairs', 'parent_identifier' => 'parent',
                    'active' => $excluded !== 'source', 'magento_category_id' => $categoryId],
                ['identifier' => 'parent', 'parent_identifier' => '', 'active' => $excluded !== 'source parent'],
            ],
            'target' => [
                ['identifier' => (string)$categoryId, 'parent_identifier' => '2', 'active' => $excluded !== 'target'],
                ['identifier' => '2', 'parent_identifier' => '', 'active' => $excluded !== 'target parent'],
            ],
        ]);
        $mappingProvider = $objects->create(CategoryDataMappingProvider::class, ['stateProvider' => $state]);
        $mapper = $this->createStub(CategoryAttributeValueMapper::class);
        $mapper->method('mapForSynchronization')->willReturn([
            'values' => ['name' => [0 => 'Imported']], 'clear' => [],
        ]);
        $writer = $objects->create(CategoryAttributeWriter::class, ['valueMapper' => $mapper]);
        $cache = $this->createMock(CategoryCacheInvalidator::class);
        if ($excluded === '') {
            $cache->expects(self::once())->method('invalidateCategories')->with([$categoryId]);
        } else {
            $cache->expects(self::never())->method('invalidateCategories');
        }
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $backfiller = $objects->create(CategoryMappedAttributeBackfiller::class, [
            'attributePreparation' => $this->createStub(CategoryAttributeSourcePreparation::class),
            'attributeWriter' => $writer, 'configProvider' => $config,
            'mappingProvider' => $mappingProvider, 'cacheInvalidator' => $cache,
        ]);
        $stats = $backfiller->execute();
        self::assertSame($excluded === '' ? 1 : 0, $stats['categories']);
        self::assertSame(
            $excluded === '' ? 'Imported' : 'Manually maintained',
            $objects->get(CategoryRepositoryInterface::class)->get($categoryId, 0)->getName()
        );
        // Repeated identical input must not flush the frontend a second time.
        self::assertSame(0, $backfiller->execute()['values']);
    }

    public function testInvalidatesCategoryAfterPartialWriteFailure(): void
    {
        $objects = Bootstrap::getObjectManager();
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category')->getId();
        $treeId = $this->seed($categoryId);
        $provider = $this->createStub(CategoryDataMappingProvider::class);
        $provider->method('getValidMappingsByCodes')->willReturn([
            'chairs' => [['category_tree_id' => $treeId, 'magento_category_id' => $categoryId]],
        ]);
        $writer = $this->createMock(CategoryAttributeWriter::class);
        $writer->expects(self::once())->method('writeMappedValues')
            ->willThrowException(new RuntimeException('partial'));
        $cache = $this->createMock(CategoryCacheInvalidator::class);
        $cache->expects(self::once())->method('invalidateCategories')->with([$categoryId]);
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $stats = $objects->create(CategoryMappedAttributeBackfiller::class, [
            'attributePreparation' => $this->createStub(CategoryAttributeSourcePreparation::class),
            'attributeWriter' => $writer, 'configProvider' => $config, 'logger' => new NullLogger(),
            'mappingProvider' => $provider, 'cacheInvalidator' => $cache,
        ])->execute();
        self::assertSame(['categories' => 0, 'values' => 0, 'errors' => 1], $stats);
    }

    #[Cache('full_page', true)]
    public function testBackfillRemovesPreviouslyCachedPage(): void
    {
        $objects = Bootstrap::getObjectManager();
        $categoryId = (int)DataFixtureStorageManager::getStorage()->get('category')->getId();
        $treeId = $this->seed($categoryId);
        $provider = $this->createStub(CategoryDataMappingProvider::class);
        $provider->method('getValidMappingsByCodes')->willReturn([
            'chairs' => [['category_tree_id' => $treeId, 'magento_category_id' => $categoryId]],
        ]);
        $writer = $this->createStub(CategoryAttributeWriter::class);
        $writer->method('writeMappedValues')->willReturn(1);
        $config = $this->createStub(CategoryAttributeConfigProvider::class);
        $config->method('isAttributeSynchronizationEnabled')->willReturn(true);
        $cache = $objects->get(PageCache::class);
        $cacheKey = 'category_backfill_' . $categoryId;
        $cache->save('old category page', $cacheKey);
        self::assertSame('old category page', $cache->load($cacheKey));
        try {
            $objects->create(CategoryMappedAttributeBackfiller::class, [
                'attributePreparation' => $this->createStub(CategoryAttributeSourcePreparation::class),
                'attributeWriter' => $writer, 'configProvider' => $config, 'mappingProvider' => $provider,
            ])->execute();
            self::assertFalse($cache->load($cacheKey));
        } finally {
            $cache->remove($cacheKey);
        }
    }

    /** @return array<string, array{string}> */
    public static function excludedNodes(): array
    {
        $cases = [];
        foreach (['', 'source', 'source parent', 'target', 'target parent', 'tree'] as $node) {
            $cases[$node === '' ? 'included' : $node] = [$node];
        }
        return $cases;
    }

    private function seed(int $categoryId): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $connection->insert($resource->getTableName('ergonode_category_tree'), [
            'tree_code' => 'backfill', 'root_category_id' => 2, 'is_active' => 1, 'remove_missing' => 0,
        ]);
        $treeId = (int)$connection->lastInsertId();
        $connection->insert($resource->getTableName('ergonode_category_mapping'), [
            'category_tree_id' => $treeId, 'ergonode_category_code' => 'chairs', 'magento_category_id' => $categoryId,
        ]);
        $connection->insert($resource->getTableName('ergonode_category_snapshot'), [
            'category_tree_id' => $treeId, 'category_code' => 'chairs', 'labels_json' => '{}',
            'raw_json' => '{}', 'content_hash' => str_repeat('a', 64),
        ]);
        $connection->insert($resource->getTableName('ergonode_category_entity_snapshot'), [
            'category_code' => 'chairs', 'labels_json' => '{}', 'attributes_json' => '[]',
            'raw_json' => '{}', 'content_hash' => str_repeat('a', 64),
        ]);
        return $treeId;
    }
}
