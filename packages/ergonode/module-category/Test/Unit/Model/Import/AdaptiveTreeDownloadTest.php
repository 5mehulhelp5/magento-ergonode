<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\CategoryTreeDownloader;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;

use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;

use Ergonode\Category\Model\Import\CategoryNormalizer;

use Ergonode\Category\Model\Import\CategoryTreeDownloadScope;
use Ergonode\Category\Model\Import\CategoryTreePageReader;
use Ergonode\Category\Model\Import\FreshCategoryTreeLoader;
use Ergonode\Category\Model\Import\PaginationStateResolver;
use Ergonode\Category\Model\Import\TreePageSizePolicy;
use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\Core\Model\GraphQl\PageQueryRetrier;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdaptiveTreeDownloadTest extends TestCase
{
    public function testDefaultStartDownloads1871CategoriesInThreeRequestsAndRestartsAt700(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $calls = [];
        $client->expects(self::exactly(6))->method('query')->willReturnCallback(
            function (string $document, array $variables) use (&$calls): array {
                $calls[] = [$variables['first'], $variables['after']];
                return $this->page($variables, 1871);
            }
        );
        $writer = $this->writer(1871, 2);
        $loader = $this->loader($client, $writer, $this->policy());

        foreach ([1, 2] as $iteration) {
            $result = $loader->load(7);
            self::assertTrue($result['complete']);
            self::assertSame(3, $result['pages']);
            self::assertSame(900, $result['page_size']);
        }
        self::assertSame([
            [700, null], [800, '700'], [900, '1500'],
            [700, null], [800, '700'], [900, '1500'],
        ], $calls);
    }

    public function testTimeoutRetriesSameCursorAndDoesNotImmediatelyGrowAfterFallback(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $calls = [];
        $client->expects(self::exactly(5))->method('query')->willReturnCallback(
            function (string $document, array $variables) use (&$calls): array {
                $calls[] = [$variables['first'], $variables['after']];
                if (count($calls) === 2) {
                    throw new LocalizedException(__('GraphQL request timed out.'));
                }
                return $this->page($variables, 1900);
            }
        );
        $this->loader($client, $this->writer(1900), $this->policy())->load(7);

        self::assertSame([[700, null], [800, '700'], [500, '700'], [500, '1200'], [600, '1700']], $calls);
    }

    public function testUsesSmallerNextSizeWithoutReplayingAcceptedPages(): void
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('700');
        $policy = $this->getMockBuilder(TreePageSizePolicy::class)
            ->setConstructorArgs([$config])->onlyMethods(['nextSize'])->getMock();
        $policy->expects(self::exactly(2))->method('nextSize')->willReturnCallback(
            static function (int $size, int $count, float $seconds): int {
                self::assertSame($size, $count);
                self::assertGreaterThanOrEqual(0.0, $seconds);
                return $size - 100;
            }
        );
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $calls = [];
        $client->expects(self::exactly(3))->method('query')->willReturnCallback(
            function (string $document, array $variables) use (&$calls): array {
                $calls[] = [$variables['first'], $variables['after']];
                return $this->page($variables, 1500);
            }
        );
        $this->loader($client, $this->writer(1500), $policy)->load(7);

        self::assertSame([[700, null], [600, '700'], [500, '1300']], $calls);
    }

    #[DataProvider('blockingFailures')]
    public function testAuthorizationAndRateLimitDoNotResizeOrWriteSnapshot(string $type, int $status): void
    {
        $failure = new GraphQlRequestException('Blocked.', $type, $status, $status === 429 ? 7 : null);
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::once())->method('query')->willThrowException($failure);
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::never())->method('replaceCompleteSnapshot');

        try {
            $this->loader($client, $writer, $this->policy())->load(7);
            self::fail('The original failure must stop this download.');
        } catch (GraphQlRequestException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public static function blockingFailures(): array
    {
        return [[GraphQlRequestException::FAILURE_AUTHORIZATION, 401],
            [GraphQlRequestException::FAILURE_AUTHORIZATION, 403],
            [GraphQlRequestException::FAILURE_RATE_LIMIT, 429]];
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function page(array $variables, int $total): array
    {
        $offset = (int)($variables['after'] ?? 0);
        $end = min($total, $offset + $variables['first']);
        $edges = [];
        for ($index = $offset; $index < $end; ++$index) {
            $edges[] = ['node' => [
                'category' => ['code' => 'c_' . $index, 'name' => [
                    ['language' => 'pl_PL', 'value' => 'Name ' . $index],
                ]],
                'parentCategory' => $index === 0 ? null : ['code' => 'c_0'],
            ]];
        }

        return ['categoryTree' => ['code' => 'main', 'categoryTreeLeafList' => [
            'edges' => $edges, 'totalCount' => $total,
            'pageInfo' => ['hasNextPage' => $end < $total, 'endCursor' => (string)$end],
        ]]];
    }

    private function writer(int $total, int $times = 1): CategorySnapshotWriter
    {
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::exactly($times))->method('replaceCompleteSnapshot')->with(7, self::callback(
            static function (array $rows) use ($total): bool {
                self::assertCount($total, $rows);
                self::assertSame(
                    array_map(static fn (int $i): string => 'c_' . $i, range(0, $total - 1)),
                    array_column($rows, 'code')
                );
                self::assertSame(range(0, $total - 1), array_column($rows, 'sort_order'));
                self::assertSame('c_0', $rows[$total - 1]['parent_code']);
                return true;
            }
        ))->willReturn(['inserted' => $total, 'updated' => 0, 'unchanged' => 0, 'removed' => 0]);

        return $writer;
    }

    private function policy(): TreePageSizePolicy
    {
        return new TreePageSizePolicy($this->createStub(ScopeConfigInterface::class));
    }

    private function loader(
        GraphQlQueryClientInterface $client,
        CategorySnapshotWriter $writer,
        TreePageSizePolicy $policy
    ): FreshCategoryTreeLoader {
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $treeQuery->method('getById')->willReturn(['tree_code' => 'main', 'is_active' => true]);

        return new FreshCategoryTreeLoader(
            new CategoryTreeDownloader(
                new CategoryTreePageReader(
                    $client,
                    $this->createStub(GraphQlWriteScopeQueryClientInterface::class),
                    $this->createStub(LanguageStoreMappingProviderInterface::class),
                    new PageQueryRetrier(),
                    $policy
                ),
                new CategoryNormalizer(new Json()),
                new PaginationStateResolver(),
                $policy,
                new CategoryTreeDownloadScope()
            ),
            $writer,
            $treeQuery,
            $this->createStub(CategoryTreeSourceState::class),
            $this->createStub(CategoryMappingQuery::class)
        );
    }
}
