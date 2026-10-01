<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Test\Unit\Model\Sync;

use Ergonode\Core\Api\GraphQlWriteScopeQueryClientInterface;
use Ergonode\ProductPublisher\Model\Sync\NewProductVisibility;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NewProductVisibilityTest extends TestCase
{
    public function testReadsAtMostFiftySkusPerRequestAndPreservesNumericStrings(): void
    {
        $skus = array_map(static fn (int $id): string => (string)$id, range(1000000100, 1000000150));
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $sizes = [];
        $client->expects(self::exactly(2))->method('queryWriteScope')->willReturnCallback(
            static function (string $document, array $variables) use (&$sizes): array {
                $sizes[] = count($variables);
                self::assertStringContainsString('$sku_0: Sku!', $document);
                self::assertStringContainsString('product_0: product(sku: $sku_0) { sku }', $document);
                $data = [];
                foreach (array_values($variables) as $index => $sku) {
                    self::assertIsString($sku);
                    $data['product_' . $index] = $sku === '1000000100' ? null : ['sku' => $sku];
                }
                return $data;
            }
        );

        self::assertSame(array_slice($skus, 1), (new NewProductVisibility($client))->load($skus));
        self::assertSame([50, 1], $sizes);
    }

    public function testEmptySelectionDoesNotQuery(): void
    {
        $client = $this->createMock(GraphQlWriteScopeQueryClientInterface::class);
        $client->expects(self::never())->method('queryWriteScope');

        self::assertSame([], (new NewProductVisibility($client))->load([]));
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidResponses')]
    public function testInvalidResponseIsNotTreatedAsAnAbsentProduct(array $data): void
    {
        $client = $this->createStub(GraphQlWriteScopeQueryClientInterface::class);
        $client->method('queryWriteScope')->willReturn($data);
        $this->expectException(LocalizedException::class);

        (new NewProductVisibility($client))->load(['1000000146']);
    }

    public static function invalidResponses(): array
    {
        return [
            'missing alias' => [[]],
            'wrong sku' => [['product_0' => ['sku' => 'other']]],
            'invalid shape' => [['product_0' => false]],
        ];
    }
}
