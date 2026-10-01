<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Integration\Model\Import;

use Ergonode\Category\Model\Import\CategoryTreePageReader;
use Ergonode\Category\Model\Import\CategoryTreeDownloader;
use Ergonode\Category\Model\Import\FreshCategoryTreeLoader;
use Ergonode\Category\Model\Import\TreePageSizePolicy;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppIsolation(true), DbIsolation(true)]
class AdaptiveTreeDownloadIntegrationTest extends TestCase
{
    public function testMagentoLoadsDefault700(): void
    {
        self::assertSame(700, Bootstrap::getObjectManager()->get(TreePageSizePolicy::class)->initialSize());
    }

    #[Config('ergonode_categories/tree/initial_page_size', '750')]
    public function testConfiguredSizeIsUsedAndAllPagesReachTheSnapshot(): void
    {
        $om = Bootstrap::getObjectManager();
        $resource = $om->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $treeTable = $resource->getTableName('ergonode_category_tree');
        $connection->insert($treeTable, [
            'tree_code' => 'adaptive-test', 'root_category_id' => 990101, 'is_active' => 1,
        ]);
        $treeId = (int)$connection->lastInsertId($treeTable);
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $calls = [];
        $client->expects(self::exactly(2))->method('query')->willReturnCallback(
            static function (string $document, array $variables) use (&$calls): array {
                $calls[] = [$variables['first'], $variables['after']];
                $start = (int)($variables['after'] ?? 0);
                $end = min(751, $start + $variables['first']);
                $edges = [];
                for ($index = $start; $index < $end; ++$index) {
                    $edges[] = ['node' => [
                        'category' => ['code' => 'node-' . $index, 'name' => [
                            ['language' => 'pl_PL', 'value' => 'Nazwa ' . $index],
                            ['language' => 'en_GB', 'value' => 'Name ' . $index],
                        ]],
                        'parentCategory' => $index === 0 ? null : ['code' => 'node-0'],
                    ]];
                }

                return ['categoryTree' => ['code' => 'adaptive-test', 'categoryTreeLeafList' => [
                    'edges' => $edges, 'totalCount' => 751,
                    'pageInfo' => ['hasNextPage' => $end < 751, 'endCursor' => (string)$end],
                ]]];
            }
        );
        $languages = $this->createStub(LanguageStoreMappingProviderInterface::class);
        $languages->method('getLanguageCodes')->willReturn(['pl_PL', 'en_GB']);
        $reader = $om->create(CategoryTreePageReader::class, [
            'readClient' => $client,
            'languageMappingProvider' => $languages,
        ]);

        $result = $om->create(FreshCategoryTreeLoader::class, [
            'downloader' => $om->create(CategoryTreeDownloader::class, ['pageReader' => $reader]),
        ])->load($treeId);

        self::assertSame([[750, null], [850, '750']], $calls);
        self::assertSame(2, $result['pages']);
        self::assertTrue($result['complete']);
        $cache = $om->get(CategoryCacheProvider::class);
        $cache->clearCache();
        $rows = $cache->getRowsByCode($treeId);
        self::assertCount(751, $rows);
        self::assertSame(750, $rows['node-750']['sort_order']);
        self::assertSame('node-0', $rows['node-750']['parent_code']);
        self::assertSame(['en_GB' => 'Name 750', 'pl_PL' => 'Nazwa 750'], $rows['node-750']['labels']);
    }
}
