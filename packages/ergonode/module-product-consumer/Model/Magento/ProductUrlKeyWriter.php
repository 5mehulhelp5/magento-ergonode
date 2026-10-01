<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;

class ProductUrlKeyWriter
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @param array<int, float|int|string|string[]> $values
     * @param int[] $clearStoreIds
     */
    public function write(int $productId, array $values, array $clearStoreIds): void
    {
        $storeIds = array_values(array_unique([
            ...array_map('intval', array_keys($values)),
            ...array_map('intval', $clearStoreIds),
        ]));
        sort($storeIds);

        foreach ($storeIds as $storeId) {
            /** @var Product $product */
            $product = $this->productRepository->getById($productId, false, $storeId, true);
            $product->setStoreId($storeId);
            $product->setData(
                'url_key',
                array_key_exists($storeId, $values) ? $values[$storeId] : ($storeId === 0 ? null : false)
            );
            $this->productRepository->save($product);
        }
    }
}
