<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

interface ProductStateSynchronizerInterface
{
    /**
     * Synchronize optional state derived from an imported product.
     *
     * @param int $productId
     * @param string $magentoSku
     * @param RemoteProduct $source
     * @return void
     */
    public function synchronize(int $productId, string $magentoSku, RemoteProduct $source): void;
}
