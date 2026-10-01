<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

interface ProductImportHashProviderInterface
{
    /**
     * Return a deterministic hash contribution for optional remote product state.
     *
     * @param RemoteProduct $source
     * @return string
     */
    public function getHash(RemoteProduct $source): string;
}
