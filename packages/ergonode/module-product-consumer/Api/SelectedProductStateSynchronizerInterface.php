<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

interface SelectedProductStateSynchronizerInterface
{
    /** @param list<string> $attributeCodes Magento attributes permitted by the target attribute set. */
    public function synchronizeSelected(int $productId, string $magentoSku, RemoteProduct $source, array $attributeCodes): void;
}
