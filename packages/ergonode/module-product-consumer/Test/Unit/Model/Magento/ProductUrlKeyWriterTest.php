<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Test\Unit\Model\Magento;

use Ergonode\ProductConsumer\Model\Magento\ProductUrlKeyWriter;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class ProductUrlKeyWriterTest extends TestCase
{
    public function testSavesMappedValuesAndStoreFallbackThroughMagentoProductLifecycle(): void
    {
        $defaultProduct = $this->productExpectingUrlKey(0, 'main-key');
        $storeProduct = $this->productExpectingUrlKey(2, 'store-key');
        $fallbackProduct = $this->productExpectingUrlKey(3, false);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::exactly(3))
            ->method('getById')
            ->willReturnCallback(static function (
                int $productId,
                bool $editMode,
                int $storeId,
                bool $forceReload
            ) use (
                $defaultProduct,
                $storeProduct,
                $fallbackProduct
            ): Product {
                self::assertSame(23, $productId);
                self::assertFalse($editMode);
                self::assertTrue($forceReload);

                return match ($storeId) {
                    0 => $defaultProduct,
                    2 => $storeProduct,
                    3 => $fallbackProduct,
                };
            });
        $repository->expects(self::exactly(3))
            ->method('save')
            ->with(self::isInstanceOf(Product::class));

        (new ProductUrlKeyWriter($repository))->write(
            23,
            [2 => 'store-key', 0 => 'main-key'],
            [3, 2]
        );
    }

    public function testClearingDefaultScopeLetsMagentoGenerateUrlKeyFromName(): void
    {
        $product = $this->productExpectingUrlKey(0, null);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getById')
            ->with(23, false, 0, true)
            ->willReturn($product);
        $repository->expects(self::once())->method('save')->with($product);

        (new ProductUrlKeyWriter($repository))->write(23, [], [0]);
    }

    private function productExpectingUrlKey(int $storeId, mixed $urlKey): Product
    {
        $product = $this->createMock(Product::class);
        $product->expects(self::once())->method('setStoreId')->with($storeId)->willReturnSelf();
        $product->expects(self::once())->method('setData')->with('url_key', $urlKey)->willReturnSelf();

        return $product;
    }
}
