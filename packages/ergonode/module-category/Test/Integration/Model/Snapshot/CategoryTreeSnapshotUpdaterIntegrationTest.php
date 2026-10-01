<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Integration\Model\Snapshot;

use Ergonode\Category\Model\GraphQl\CategoryQueries;

use Ergonode\Category\Api\CategoryTreeSnapshotUpdaterInterface;
use Ergonode\Category\Model\Import\CategoryDetailsLoader;
use Ergonode\Category\Model\Import\CategoryNormalizer;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[AppIsolation(true), DbIsolation(true)]
class CategoryTreeSnapshotUpdaterIntegrationTest extends TestCase
{
    #[DataProvider('confirmationModes')]
    public function testAcceptedTreeIsStoredWithoutLosingTranslationsIdentitiesOrMappings(bool $confirmed): void
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $snapshotTable = $resource->getTableName('ergonode_category_snapshot');
        $mappingTable = $resource->getTableName('ergonode_category_mapping');
        $connection->insert($treeTable, ['tree_code' => 'snapshot-test', 'root_category_id' => 990001]);
        $treeId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
        $normalizer = $objectManager->get(CategoryNormalizer::class);
        $root = $normalizer->normalizeTreeNode([
            'category' => ['code' => 'root', 'name' => [
                ['language' => 'pl_PL', 'value' => 'Meble'],
                ['language' => 'en_GB', 'value' => 'Furniture'],
            ]],
            'parentCategory' => null,
        ], 0);
        $removed = $normalizer->normalizeTreeNode([
            'category' => ['code' => 'removed', 'name' => []], 'parentCategory' => null,
        ], 1);
        $objectManager->get(CategorySnapshotWriter::class)->replaceCompleteSnapshot($treeId, [$root, $removed]);
        $connection->insertMultiple($mappingTable, [
            ['category_tree_id' => $treeId, 'ergonode_category_code' => 'root',
                'ergonode_category_id' => 'root-id', 'magento_category_id' => 990002],
            ['category_tree_id' => $treeId, 'ergonode_category_code' => 'new-0',
                'ergonode_category_id' => 'new-id', 'magento_category_id' => 990003],
            ['category_tree_id' => $treeId, 'ergonode_category_code' => 'removed',
                'ergonode_category_id' => 'removed-id', 'magento_category_id' => 990004],
        ]);
        $mappingQuery = $connection->select()->from($mappingTable)->where('category_tree_id = ?', $treeId);
        $mappingsBefore = $connection->fetchAll($mappingQuery);
        $cache = $objectManager->get(CategoryCacheProvider::class);
        $cache->clearCache();
        self::assertCount(2, $cache->getRows($treeId));

        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::exactly($confirmed ? 0 : 1))->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables): array {
                self::assertSame(50, substr_count($document, ': category(code:'));
                $result = [];
                for ($index = 0; $index < 50; $index++) {
                    $result['category_' . $index] = ['code' => $variables['code_' . $index], 'name' => [
                        ['language' => 'pl_PL', 'value' => 'Zdalna nazwa ' . $index],
                        ['language' => 'en_GB', 'value' => 'Remote name ' . $index],
                    ]];
                }

                return $result;
            }
        );
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL', 'en_GB']);
        $objectManager->addSharedInstance(new CategoryDetailsLoader(
            $client,
            $languages,
            new CategoryQueries()
        ), CategoryDetailsLoader::class);
        $updater = $objectManager->create(CategoryTreeSnapshotUpdaterInterface::class);
        $layout = [['code' => 'root', 'parent_code' => null]];
        $details = [];
        for ($index = 0; $index < 50; $index++) {
            $layout[] = ['code' => 'new-' . $index, 'parent_code' => 'root'];
            $details['new-' . $index] = ['code' => 'new-' . $index, 'name' => [
                ['language' => 'pl_PL', 'value' => 'Zdalna nazwa ' . $index],
                ['language' => 'en_GB', 'value' => 'Remote name ' . $index],
            ]];
        }

        $updater->update($treeId, $layout, $confirmed ? $details : []);

        $rows = $cache->getRowsByCode($treeId);
        self::assertCount(51, $rows);
        self::assertArrayNotHasKey('removed', $rows);
        self::assertSame($root['labels'], $rows['root']['labels']);
        self::assertSame('root', $rows['new-49']['parent_code']);
        self::assertSame(50, $rows['new-49']['sort_order']);
        self::assertSame('Remote name 0', $rows['new-0']['labels']['en_GB']);
        self::assertSame('new-id', $rows['new-0']['ergonode_category_id']);
        self::assertSame(990003, $rows['new-0']['magento_category_id']);
        self::assertSame($mappingsBefore, $connection->fetchAll($mappingQuery));
        self::assertSame($root['hash'], $connection->fetchOne(
            $connection->select()->from($snapshotTable, ['content_hash'])
                ->where('category_tree_id = ?', $treeId)->where('category_code = ?', 'root')
        ));

        $updater->update($treeId, $layout, []);
        self::assertSame($rows, $cache->getRowsByCode($treeId));
    }

    public static function confirmationModes(): array
    {
        return [[true], [false]];
    }
}
