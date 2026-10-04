<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\ProductConsumer\Api\SelectedProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

class SelectedProductStateSynchronizerPool
{
    /** @param SelectedProductStateSynchronizerInterface[] $synchronizers */
    public function __construct(private readonly array $synchronizers = [])
    {
    }

    public function synchronize(int $productId, string $magentoSku, RemoteProduct $source, array $attributeCodes): void
    {
        foreach ($this->synchronizers as $synchronizer) {
            $synchronizer->synchronizeSelected($productId, $magentoSku, $source, $attributeCodes);
        }
    }
}
