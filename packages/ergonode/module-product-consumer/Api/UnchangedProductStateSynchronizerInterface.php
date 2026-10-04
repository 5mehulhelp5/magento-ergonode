<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

interface UnchangedProductStateSynchronizerInterface extends ProductStateSynchronizerInterface
{
    /** Called only by a new import pass, without rewriting ordinary product attributes. */
    public function synchronizeUnchanged(int $productId, string $magentoSku, RemoteProduct $source): void;
}
