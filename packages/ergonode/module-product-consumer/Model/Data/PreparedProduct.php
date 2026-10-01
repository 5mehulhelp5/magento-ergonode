<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Data;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductConsumer\Api\ProductTypeAdapterInterface;

final readonly class PreparedProduct
{
    public function __construct(
        public int $productId,
        public ProductTypeAdapterInterface $typeAdapter,
        public string $magentoSku,
        public string $currentMagentoSku,
        public string $identityMode = ProductIdentityInterface::MODE_SHARED
    ) {
    }
}
