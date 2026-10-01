<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\GraphQlQueryClientInterface;
use Ergonode\ProductCategoryConsumer\Model\GraphQl\ProductCategoryQueries;
use Ergonode\ProductCategoryConsumer\Model\GraphQl\RemoteProductCategoryCodeLoader;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteProductCategoryCodeLoaderTest extends TestCase
{
    public function testLoadsAllPagesAndCachesCodesPerSku(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(2))
            ->method('query')
            ->with(ProductCategoryQueries::CATEGORIES, self::isType('array'))
            ->willReturnOnConsecutiveCalls(
                $this->product(['chairs'], true, 'next'),
                $this->product(['sale', 'chairs'])
            );
        $loader = new RemoteProductCategoryCodeLoader($client);

        self::assertSame(['chairs', 'sale'], $loader->load('SKU-1'));
        self::assertSame(['chairs', 'sale'], $loader->load('SKU-1'));
    }

    #[DataProvider('incompleteConnections')]
    public function testRejectsIncompleteCategoryConnection(mixed $connection): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::once())->method('query')->willReturn(['product' => [
            'sku' => 'SKU-1',
            'categoryList' => $connection,
        ]]);

        $this->expectException(LocalizedException::class);
        (new RemoteProductCategoryCodeLoader($client))->load('SKU-1');
    }

    /** @return iterable<string, array{mixed}> */
    public static function incompleteConnections(): iterable
    {
        yield 'missing connection' => [null];
        yield 'nullable edges' => [['edges' => null, 'pageInfo' => ['hasNextPage' => false]]];
        yield 'nullable pageInfo' => [['edges' => [], 'pageInfo' => null]];
        yield 'missing hasNextPage' => [['edges' => [], 'pageInfo' => []]];
        yield 'nullable hasNextPage' => [['edges' => [], 'pageInfo' => ['hasNextPage' => null]]];
        yield 'invalid hasNextPage' => [['edges' => [], 'pageInfo' => ['hasNextPage' => 'false']]];
        yield 'missing node' => [['edges' => [[]], 'pageInfo' => ['hasNextPage' => false]]];
        yield 'nullable code' => [['edges' => [['node' => ['code' => null]]], 'pageInfo' => ['hasNextPage' => false]]];
        yield 'blank code' => [['edges' => [['node' => ['code' => ' ']]], 'pageInfo' => ['hasNextPage' => false]]];
        yield 'invalid code' => [['edges' => [['node' => ['code' => 7]]], 'pageInfo' => ['hasNextPage' => false]]];
    }

    public function testAcceptsExplicitlyEmptyCategoryList(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::once())->method('query')->willReturn($this->product([]));

        self::assertSame([], (new RemoteProductCategoryCodeLoader($client))->load('SKU-1'));
    }

    public function testIncompleteLaterPageDoesNotCachePartialResult(): void
    {
        $client = $this->createMock(GraphQlQueryClientInterface::class);
        $client->expects(self::exactly(4))->method('query')->willReturnOnConsecutiveCalls(
            $this->product(['chairs'], true, 'next'),
            ['product' => ['sku' => 'SKU-1', 'categoryList' => ['edges' => null, 'pageInfo' => null]]],
            $this->product(['chairs'], true, 'next'),
            $this->product(['sale'])
        );
        $loader = new RemoteProductCategoryCodeLoader($client);

        try {
            $loader->load('SKU-1');
            self::fail('Incomplete second page must not be accepted.');
        } catch (LocalizedException) {
            self::assertSame(['chairs', 'sale'], $loader->load('SKU-1'));
        }
    }

    /** @param string[] $codes @return array<string, mixed> */
    private function product(array $codes, bool $hasNext = false, ?string $cursor = null): array
    {
        return ['product' => [
            'sku' => 'SKU-1',
            'categoryList' => [
                'pageInfo' => ['hasNextPage' => $hasNext, 'endCursor' => $cursor],
                'edges' => array_map(static fn (string $code): array => ['node' => ['code' => $code]], $codes),
            ],
        ]];
    }
}
