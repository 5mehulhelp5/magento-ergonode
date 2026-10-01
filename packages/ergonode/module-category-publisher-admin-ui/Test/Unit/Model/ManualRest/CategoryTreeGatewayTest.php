<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Test\Unit\Model\ManualRest;

use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeIdentityCache;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\CategoryTreeGateway;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\Client;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\RetryableRequestException;
use Ergonode\CategoryPublisherAdminUi\Model\ManualRest\RequestException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryTreeGatewayTest extends TestCase
{
    public function testSingleNumericCodeUsesExactTargetedLookup(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('get')
            ->with('categories?limit=50&offset=0&filter=code%3D123&view=list')
            ->willReturn(['collection' => [['code' => '123', 'id' => 'numeric-id']]]);
        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        self::assertSame([123 => 'numeric-id'], iterator_to_array($gateway->getCategoryIds(['123'])));
    }

    public function testCachesOnlyIdentityAndReadsCurrentTreeOnEverySave(): void
    {
        $cache = $this->createMock(CategoryTreeIdentityCache::class);
        $cache->expects(self::exactly(2))->method('get')->with('main')->willReturn(null, 'tree-id');
        $cache->expects(self::once())->method('save')->with('main', 'tree-id');
        $client = $this->createMock(Client::class);
        $resources = [];
        $client->expects(self::exactly(3))->method('get')->willReturnCallback(
            static function (string $resource) use (&$resources): array {
                $resources[] = $resource;
                if (str_starts_with($resource, 'trees?')) {
                    return ['collection' => [['code' => 'main', 'id' => 'tree-id']]];
                }
                return ['code' => 'main', 'categories' => [['category_id' => 'version-' . count($resources)]]];
            }
        );
        $gateway = new CategoryTreeGateway($client, $cache);
        $first = $gateway->getTree('main');
        $second = $gateway->getTree('main');
        self::assertSame('tree-id', $first['id']);
        self::assertNotSame($first['categories'], $second['categories']);
        self::assertSame('trees/tree-id', $resources[2]);
    }

    #[DataProvider('staleIdentities')]
    public function testStaleIdentityIsResolvedOnceAgain(bool $notFound): void
    {
        $cache = $this->createMock(CategoryTreeIdentityCache::class);
        $cache->expects(self::once())->method('get')->with('main')->willReturn('old-id');
        $cache->expects(self::once())->method('remove')->with('main');
        $cache->expects(self::once())->method('save')->with('main', 'new-id');
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(3))->method('get')->willReturnCallback(
            static function (string $resource) use ($notFound): array {
                if ($resource === 'trees/old-id') {
                    if ($notFound) {
                        throw new RequestException(404);
                    }
                    return ['code' => 'renamed-tree'];
                }
                if (str_starts_with($resource, 'trees?')) {
                    return ['collection' => [['code' => 'main', 'id' => 'new-id']]];
                }
                self::assertSame('trees/new-id', $resource);
                return ['code' => 'main', 'categories' => []];
            }
        );
        self::assertSame('new-id', (new CategoryTreeGateway($client, $cache))->getTree('main')['id']);
    }

    public static function staleIdentities(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('nonStaleFailures')]
    public function testAuthorizationRateLimitAndServerErrorsDoNotStartAnotherLookup(int $status): void
    {
        $exception = match ($status) {
            401 => new AuthenticationException(__('Expired')),
            403 => new LocalizedException(__('Forbidden')),
            429 => new RetryableRequestException('Rate limit', 5),
            default => new RequestException($status),
        };
        $cache = $this->createMock(CategoryTreeIdentityCache::class);
        $cache->expects(self::once())->method('get')->willReturn('tree-id');
        $cache->expects(self::never())->method('remove');
        $cache->expects(self::never())->method('save');
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('get')->with('trees/tree-id')->willThrowException($exception);
        $this->expectException($exception::class);
        (new CategoryTreeGateway($client, $cache))->getTree('main');
    }

    public static function nonStaleFailures(): array
    {
        return [[401], [403], [429], [500]];
    }

    public function testFiftyRecentIdentitiesNeedOneRestRequest(): void
    {
        $rows = [];
        $expected = [];
        for ($index = 1; $index <= 50; ++$index) {
            $rows[] = ['code' => 'c_' . $index, 'id' => 'id_' . $index];
            $expected['c_' . $index] = 'id_' . $index;
        }
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('get')
            ->with('categories?limit=50&offset=0&field=sequence&order=DESC&view=list')
            ->willReturn(['collection' => $rows]);

        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        self::assertSame($expected, iterator_to_array(
            $gateway->getCategoryIds(array_keys($expected))
        ));
    }

    public function testConcurrentNewerCategoriesDoNotForceIndividualLookups(): void
    {
        $first = [['code' => 'c_1', 'id' => 'id_1']];
        for ($index = 1; $index < 50; ++$index) {
            $first[] = ['code' => 'unrelated_' . $index, 'id' => 'other_' . $index];
        }
        $client = $this->createMock(Client::class);
        $offsets = [];
        $client->expects(self::exactly(2))->method('get')->willReturnCallback(
            static function (string $resource) use (&$offsets, $first): array {
                parse_str((string)parse_url($resource, PHP_URL_QUERY), $params);
                self::assertArrayNotHasKey('filter', $params);
                $offsets[] = (int)$params['offset'];
                return ['collection' => $params['offset'] === '0' ? $first : [
                    ['code' => 'c_2', 'id' => 'id_2'], ['code' => 'c_3', 'id' => 'id_3'],
                ]];
            }
        );
        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        self::assertSame(['c_1' => 'id_1', 'c_2' => 'id_2', 'c_3' => 'id_3'], iterator_to_array(
            $gateway->getCategoryIds(['c_1', 'c_2', 'c_3'])
        ));
        self::assertSame([0, 50], $offsets);
    }

    public function testSparseHistoricalCodesDoNotScanEntireCatalog(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(3))->method('get')->willReturnCallback(
            static function (string $resource): array {
                parse_str((string)parse_url($resource, PHP_URL_QUERY), $params);
                if (!isset($params['filter'])) {
                    return ['collection' => [['code' => 'unrelated', 'id' => 'other']]];
                }
                $code = substr($params['filter'], 5);
                return ['collection' => [['code' => $code, 'id' => 'id_' . $code]]];
            }
        );
        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        self::assertSame(['old_a' => 'id_old_a', 'old_b' => 'id_old_b'], iterator_to_array(
            $gateway->getCategoryIds(['old_a', 'old_b'])
        ));
    }

    public function testHistoricalBatchUsesCheaperPaginationFromReportedGridCount(): void
    {
        $client = $this->createMock(Client::class);
        $offsets = [];
        $client->expects(self::exactly(3))->method('get')->willReturnCallback(
            static function (string $resource) use (&$offsets): array {
                parse_str((string)parse_url($resource, PHP_URL_QUERY), $params);
                self::assertArrayNotHasKey('filter', $params);
                $offset = (int)$params['offset'];
                $offsets[] = $offset;
                return ['info' => ['filtered' => 150], 'collection' => array_map(
                    static fn (int $id): array => ['code' => 'c_' . $id, 'id' => 'id_' . $id],
                    range($offset + 1, $offset + 50)
                )];
            }
        );
        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        $codes = array_map(static fn (int $id): string => 'c_' . $id, range(101, 150));
        $ids = iterator_to_array($gateway->getCategoryIds($codes));
        self::assertCount(50, $ids);
        self::assertSame('id_101', $ids['c_101']);
        self::assertSame([0, 50, 100], $offsets);
    }

    public function testMissingIdentityKeepsAlreadyYieldedMatchesBeforeRetry(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            ['collection' => [['code' => 'a', 'id' => 'id_a']]],
            ['collection' => [['code' => 'b_extra', 'id' => 'wrong']]]
        );
        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        $found = [];
        try {
            foreach ($gateway->getCategoryIds(['a', 'b']) as $code => $id) {
                $found[$code] = $id;
            }
            self::fail('Missing identity must remain retryable.');
        } catch (RetryableRequestException) {
            self::assertSame(['a' => 'id_a'], $found);
        }
    }

    public function testSingleIdentityUsesOnlyTargetedLookup(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('get')
            ->with('categories?limit=50&offset=0&filter=code%3Da&view=list')
            ->willReturn(['collection' => [['code' => 'a', 'id' => 'id_a']]]);
        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        self::assertSame(['a' => 'id_a'], iterator_to_array($gateway->getCategoryIds(['a'])));
    }

    public function testEmptyCodesDoNotCallRest(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('get');
        $gateway = new CategoryTreeGateway($client, $this->createStub(CategoryTreeIdentityCache::class));
        self::assertSame([], iterator_to_array($gateway->getCategoryIds([])));
    }
}
