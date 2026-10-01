<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisher\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\ProductCategoryPublisher\Model\GraphQl\RemoteProductCategoryStateLoader;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RemoteProductCategoryStateLoaderTest extends TestCase
{
    public function testLoadsFiftyOneProductsInTwoAliasedRequests(): void
    {
        $calls = 0;
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturnCallback(
            static function (string $query, array $variables) use (&$calls): array {
                ++$calls;
                $skuVariables = array_filter(
                    array_keys($variables),
                    static fn (string $name): bool => str_starts_with($name, 'sku_')
                );
                self::assertLessThanOrEqual(50, count($skuVariables));
                self::assertSame(count($skuVariables), preg_match_all('/product_\d+: product\(/', $query));
                $result = [];
                foreach ($skuVariables as $variable) {
                    $index = (int)substr($variable, 4);
                    $sku = (string)$variables[$variable];
                    $result['product_' . $index] = self::product($sku, ['category-' . $sku]);
                }

                return $result;
            }
        );
        $skus = array_map(static fn (int $index): string => 'SKU-' . $index, range(1, 51));

        $result = (new RemoteProductCategoryStateLoader($client))->load($skus);

        self::assertSame(2, $calls);
        self::assertCount(51, $result);
        self::assertSame(['category-SKU-51'], $result['sku:SKU-51']);
    }

    public function testPaginatesAllPendingProductsInBatchesWithoutNPlusOne(): void
    {
        $calls = 0;
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturnCallback(
            static function (string $_query, array $variables) use (&$calls): array {
                ++$calls;
                if ($calls === 1) {
                    self::assertNull($variables['after_0']);
                    self::assertNull($variables['after_1']);

                    return [
                        'product_0' => self::product('1000000001', ['chairs'], true, 'cursor-1'),
                        'product_1' => self::product('SKU-2', ['tables']),
                    ];
                }
                self::assertSame(['sku_0' => '1000000001', 'after_0' => 'cursor-1'], $variables);

                return ['product_0' => self::product('1000000001', ['sale'])];
            }
        );

        $result = (new RemoteProductCategoryStateLoader($client))->load(['1000000001', 'SKU-2']);

        self::assertSame(2, $calls);
        self::assertSame(['chairs', 'sale'], $result['sku:1000000001']);
        self::assertSame(['tables'], $result['sku:SKU-2']);
    }

    #[DataProvider('incompleteConnections')]
    public function testRejectsIncompleteCategoryConnection(array $categoryList): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn([
            'product_0' => ['sku' => 'SKU-1', 'categoryList' => $categoryList],
        ]);

        $this->expectException(LocalizedException::class);
        (new RemoteProductCategoryStateLoader($client))->load(['SKU-1']);
    }

    public function testAcceptsConfirmedEmptyCategoryConnection(): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn([
            'product_0' => self::product('SKU-1', []),
        ]);

        self::assertSame(
            ['sku:SKU-1' => []],
            (new RemoteProductCategoryStateLoader($client))->load(['SKU-1'])
        );
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function incompleteConnections(): array
    {
        $edge = ['node' => ['code' => 'first-page-only']];

        return [
            'null page info' => [['edges' => [$edge], 'pageInfo' => null]],
            'missing page info' => [['edges' => [$edge]]],
            'null has-next flag' => [['edges' => [$edge], 'pageInfo' => ['hasNextPage' => null]]],
            'null edges' => [['edges' => null, 'pageInfo' => ['hasNextPage' => false]]],
            'missing edges' => [['pageInfo' => ['hasNextPage' => false]]],
        ];
    }

    /** @param string[] $categoryCodes @return array<string, mixed> */
    private static function product(
        string $sku,
        array $categoryCodes,
        bool $hasNextPage = false,
        ?string $endCursor = null
    ): array {
        return [
            'sku' => $sku,
            'categoryList' => [
                'pageInfo' => ['hasNextPage' => $hasNextPage, 'endCursor' => $endCursor],
                'edges' => array_map(
                    static fn (string $code): array => ['node' => ['code' => $code]],
                    $categoryCodes
                ),
            ],
        ];
    }
}
