<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\ProductConsumer\Api\ProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

class ProductStateSynchronizerPool
{
    /** @param ProductStateSynchronizerInterface[] $synchronizers */
    public function __construct(private readonly array $synchronizers = [])
    {
    }

    public function synchronize(int $productId, string $magentoSku, RemoteProduct $source): void
    {
        foreach ($this->synchronizers as $synchronizer) {
            $synchronizer->synchronize($productId, $magentoSku, $source);
        }
    }
}
