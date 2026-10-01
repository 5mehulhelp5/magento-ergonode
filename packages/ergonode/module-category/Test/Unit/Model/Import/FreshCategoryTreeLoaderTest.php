<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\CategoryTreeDownloader;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;

use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;

use Ergonode\Category\Model\GraphQl\CategoryQueries;

use Ergonode\Category\Model\Import\CategoryNormalizer;
use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;
use Ergonode\Category\Model\Import\CategoryTreePageReader;
use Ergonode\Category\Model\Import\FreshCategoryTreeLoader;
use Ergonode\Category\Model\Import\PaginationStateResolver;
use Ergonode\Category\Model\Import\TreePageSizePolicy;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Api\PageQueryRetrierInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class FreshCategoryTreeLoaderTest extends TestCase
{
    public function testInactiveConfigurationDoesNotOverwriteTheSourceObservation(): void
    {
        $query = $this->createStub(CategoryTreeQuery::class);
        $query->method('getById')->willReturn(['is_active' => false]);
        $reader = $this->createMock(CategoryTreePageReader::class);
        $reader->expects(self::never())->method('read');
        $state = $this->createMock(CategoryTreeSourceState::class);
        $state->expects(self::never())->method('record');
        $loader = new FreshCategoryTreeLoader(
            new CategoryTreeDownloader(
                $reader,
                $this->createStub(CategoryNormalizer::class),
                new PaginationStateResolver(),
                $this->createStub(TreePageSizePolicy::class),
                new CategoryTreeDownloadScope()
            ),
            $this->createStub(CategorySnapshotWriter::class),
            $query,
            $state,
            $this->createStub(CategoryMappingQuery::class)
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Category Tree is inactive');
        $loader->load(7);
    }

    public function testPersistsOnlyAfterAllPagesAndAssignsGlobalOrder(): void
    {
        $retrier = $this->createMock(PageQueryRetrierInterface::class);
        $retrier->expects($this->exactly(2))->method('query')->willReturnOnConsecutiveCalls(
            $this->page(['a', 'b'], true, 'cursor-1'),
            $this->page(['c'], false, null)
        );
        $normalizer = $this->createMock(CategoryNormalizer::class);
        $normalizer->expects($this->exactly(3))->method('normalizeTreeNode')
            ->willReturnCallback(static fn (array $node, int $position): array => [
                'code' => (string)$node['category']['code'],
                'parent_code' => null,
                'labels' => ['pl_PL' => (string)$node['category']['code']],
                'sort_order' => $position,
                'raw' => $node,
                'hash' => hash('sha256', (string)$node['category']['code']),
            ]);
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects($this->once())->method('replaceCompleteSnapshot')
            ->with(7, self::callback(static fn (array $rows): bool => array_column($rows, 'sort_order') === [0, 1, 2]))
            ->willReturn(['inserted' => 3, 'updated' => 0, 'unchanged' => 0, 'removed' => 0]);

        $result = $this->loader($retrier, $normalizer, $writer)->load(7);

        self::assertTrue($result['complete']);
        self::assertSame(['a', 'b', 'c'], array_column($result['categories'], 'code'));
        self::assertSame(2, $result['pages']);
    }

    public function testSecondPageFailureLeavesSnapshotUntouched(): void
    {
        $retrier = $this->createMock(PageQueryRetrierInterface::class);
        $retrier->expects($this->exactly(2))->method('query')
            ->willReturnCallback(function () {
                static $page = 0;
                if (++$page === 1) {
                    return $this->page(['a'], true, 'cursor-1');
                }
                throw new LocalizedException(__('Second page failed.'));
            });
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects($this->never())->method('replaceCompleteSnapshot');

        $this->expectException(LocalizedException::class);
        $this->loader($retrier, $this->createStub(CategoryNormalizer::class), $writer)->load(7);
    }

    public function testRejectsMissingOrRepeatedCursorBeforeWriting(): void
    {
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn($this->page(['a'], true, ''));
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects($this->never())->method('replaceCompleteSnapshot');

        $this->expectException(LocalizedException::class);
        $this->loader($retrier, $this->createStub(CategoryNormalizer::class), $writer)->load(7);
    }

    public function testMissingRemoteTreeFailsClosedWithoutReplacingSnapshot(): void
    {
        $retrier = $this->createStub(PageQueryRetrierInterface::class);
        $retrier->method('query')->willReturn(['categoryTree' => null]);
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::never())->method('replaceCompleteSnapshot');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Ergonode category tree "main" was not found.');
        $this->loader($retrier, $this->createStub(CategoryNormalizer::class), $writer)->load(7);
    }

    public function testWriteScopeRefreshUsesWriteCredentialWithoutReadClient(): void
    {
        $retrier = $this->createMock(PageQueryRetrierInterface::class);
        $retrier->expects(self::once())->method('query')->willReturnCallback(
            static function (array $pageSizes, int $pageSize, callable $query): array {
                self::assertSame([200, 100, 50, 25], $pageSizes);

                return $query($pageSize);
            }
        );
        $readClient = $this->createMock(GraphQlQueryClientInterface::class);
        $readClient->expects(self::never())->method('query');
        $writeClient = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $writeClient->expects(self::once())->method('queryWriteScope')->with(
            CategoryQueries::CATEGORY_TREE,
            [
                'code' => 'main',
                'first' => 200,
                'after' => null,
                'languages' => [],
            ]
        )->willReturn($this->page([], false, null));
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::once())->method('replaceCompleteSnapshot')->with(7, [])->willReturn([
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'removed' => 0,
        ]);

        $result = $this->loader(
            $retrier,
            $this->createStub(CategoryNormalizer::class),
            $writer,
            $readClient,
            $writeClient
        )->loadWriteScope(7);

        self::assertTrue($result['complete']);
    }

    private function loader(
        PageQueryRetrierInterface $retrier,
        CategoryNormalizer $normalizer,
        CategorySnapshotWriter $writer,
        ?GraphQlQueryClientInterface $readClient = null,
        ?GraphQlWriteScopeQueryClientInterface $writeClient = null,
        ?TreePageSizePolicy $policy = null,
        ?CategoryTreeDownloadScope $scope = null
    ): FreshCategoryTreeLoader {
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn([
            'category_tree_id' => 7,
            'tree_code' => 'main',
            'is_active' => true,
        ]);

        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('200');

        $policy ??= new TreePageSizePolicy($config);

        return new FreshCategoryTreeLoader(
            new CategoryTreeDownloader(
                new CategoryTreePageReader(
                    $readClient ?? $this->createStub(GraphQlQueryClientInterface::class),
                    $writeClient ?? $this->createStub(GraphQlWriteScopeQueryClientInterface::class),
                    $this->createStub(LanguageStoreMappingProviderInterface::class),
                    $retrier,
                    $policy
                ),
                $normalizer,
                new PaginationStateResolver(),
                $policy,
                $scope ?? new CategoryTreeDownloadScope()
            ),
            $writer,
            $treeQuery,
            $this->createStub(CategoryTreeSourceState::class),
            $this->createStub(CategoryMappingQuery::class)
        );
    }

    /**
     * @param string[] $codes
     * @return array<string, mixed>
     */
    private function page(array $codes, bool $hasNext, ?string $cursor): array
    {
        return [
            '_page_size' => 200,
            'categoryTree' => [
                'code' => 'main',
                'categoryTreeLeafList' => [
                    'edges' => array_map(
                        static fn (string $code): array => ['node' => ['category' => ['code' => $code]]],
                        $codes
                    ),
                    'pageInfo' => ['hasNextPage' => $hasNext, 'endCursor' => $cursor],
                ],
            ],
        ];
    }

    public function testOneDownloadWritesSeparateSnapshotsForTwoLocalRootsAndExpiresAfterRun(): void
    {
        $retrier = $this->createMock(PageQueryRetrierInterface::class);
        $retrier->expects(self::exactly(2))->method('query')->willReturn($this->page([], false, null));
        $written = [];
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::exactly(3))->method('replaceCompleteSnapshot')->willReturnCallback(
            static function (int $id, array $categories) use (&$written): array {
                $written[] = $id;
                self::assertSame([], $categories);
                return ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0];
            }
        );
        $scope = new CategoryTreeDownloadScope();
        $loader = $this->loader($retrier, $this->createStub(CategoryNormalizer::class), $writer, scope: $scope);
        $scope->execute(function () use ($loader): void {
            $loader->load(7);
            $loader->load(8);
        });
        $scope->execute(static fn (): array => $loader->load(7));
        self::assertSame([7, 8, 7], $written);
    }
}
