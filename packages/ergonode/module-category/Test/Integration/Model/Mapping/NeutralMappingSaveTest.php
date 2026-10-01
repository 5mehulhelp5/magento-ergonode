<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Integration\Model\Mapping;

use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;
use Ergonode\Category\Model\Mapping\CategoryLayoutSaver;
use Ergonode\Category\Model\ResourceModel\CategoryMappingSaveHandler;
use Ergonode\Core\Api\MappingVisibilityProviderInterface;
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
#[DataFixture(CategoryFixture::class, ['name' => 'Neutral name', 'parent_id' => 2], as: 'target')]
#[DataFixture(CategoryFixture::class, ['name' => 'Remapped name', 'parent_id' => 2], as: 'remap')]
class NeutralMappingSaveTest extends TestCase
{
    public static function numericCodes(): array
    {
        return ['zero' => ['0'], 'numeric' => ['123'], 'leading-zero' => ['001']];
    }

    #[DataProvider('numericCodes')]
    public function testNumericCodeSurvivesNewUnchangedAndRemappedSave(string $code): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $fixtures = DataFixtureStorageManager::getStorage();
        $targetId = (int)$fixtures->get('target')->getId();
        $remapId = (int)$fixtures->get('remap')->getId();
        $treeId = $objects->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'numeric-save', 'root_category_id' => 2, 'is_active' => true,
        ]);
        $connection->insert($resource->getTableName('ergonode_category_snapshot'), [
            'category_tree_id' => $treeId, 'category_code' => $code, 'sort_order' => 0,
            'labels_json' => '{"en_GB":"Remote name"}', 'raw_json' => '{}', 'content_hash' => hash('sha256', $code),
        ]);
        $saver = $objects->create(CategoryLayoutSaver::class, [
            'dataWriter' => $objects->create(CategoryMappingSaveHandler::class),
        ]);
        $items = [['code' => $code, 'parent_code' => null, 'sort_order' => 0, 'magento_category_id' => $targetId]];
        $visibility = [['source' => 'ergo', 'identifier' => $code, 'active' => false]];
        $stats = $saver->save($treeId, $items, $visibility);
        self::assertSame(['updated' => 1, 'unchanged' => 0, 'attribute_values' => 0], $stats);
        $stats = $saver->save($treeId, $items, $visibility);
        self::assertSame(['updated' => 0, 'unchanged' => 1, 'attribute_values' => 0], $stats);
        $items[0]['magento_category_id'] = $remapId;
        $stats = $saver->save($treeId, $items, $visibility);
        self::assertSame(['updated' => 1, 'unchanged' => 0, 'attribute_values' => 0], $stats);
        $mappingTable = $resource->getTableName('ergonode_category_mapping');
        $rows = $connection->fetchAll($connection->select()
            ->from($mappingTable, ['ergonode_category_code', 'magento_category_id'])
            ->where('category_tree_id = ?', $treeId));
        self::assertSame([['ergonode_category_code' => $code, 'magento_category_id' => (string)$remapId]], $rows);
        self::assertSame([$code => false], $objects->get(MappingVisibilityProviderInterface::class)
            ->getActiveMap('category', 'ergo', [$code], (string)$treeId));
        $categoryResource = $objects->get(CategoryResource::class);
        self::assertSame('Neutral name', $categoryResource->getAttributeRawValue($targetId, 'name', 0));
        self::assertSame('Remapped name', $categoryResource->getAttributeRawValue($remapId, 'name', 0));
    }

    public function testBaseHandlerPersistsIdentityAndVisibilityWithoutImportingValues(): void
    {
        $objects = Bootstrap::getObjectManager();
        $resource = $objects->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $id = (int)DataFixtureStorageManager::getStorage()->get('target')->getId();
        $treeId = $objects->get(CategoryTreeRepository::class)->save([
            'tree_code' => 'neutral-save', 'root_category_id' => 2, 'is_active' => true,
        ]);
        $connection->insert($resource->getTableName('ergonode_category_snapshot'), [
            'category_tree_id' => $treeId, 'category_code' => 'chairs', 'sort_order' => 0,
            'labels_json' => '{"en_GB":"Remote name"}', 'raw_json' => '{}', 'content_hash' => hash('sha256', 'chairs'),
        ]);
        $saver = $objects->create(CategoryLayoutSaver::class, [
            'dataWriter' => $objects->create(CategoryMappingSaveHandler::class),
        ]);
        self::assertSame(['updated' => 1, 'unchanged' => 0, 'attribute_values' => 0], $saver->save($treeId, [
            ['code' => 'chairs', 'parent_code' => null, 'sort_order' => 0, 'magento_category_id' => $id],
        ], [['source' => 'ergo', 'identifier' => 'chairs', 'active' => false]]));
        self::assertSame($id, (int)$connection->fetchOne($connection->select()
            ->from($resource->getTableName('ergonode_category_mapping'), ['magento_category_id'])
            ->where('category_tree_id = ?', $treeId)->where('ergonode_category_code = ?', 'chairs')));
        self::assertSame(['chairs' => false], $objects->get(MappingVisibilityProviderInterface::class)
            ->getActiveMap('category', 'ergo', ['chairs'], (string)$treeId));
        self::assertSame('Neutral name', $objects->get(CategoryResource::class)->getAttributeRawValue($id, 'name', 0));
    }
}
