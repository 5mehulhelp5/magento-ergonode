<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\ProductConsumer\Api\ProductAttributeMappingDeferrerInterface;

class ProductAttributeMappingDeferrerPool
{
    /** @param ProductAttributeMappingDeferrerInterface[] $deferrers */
    public function __construct(private readonly array $deferrers = [])
    {
    }

    /** @param array<string, mixed> $mapping */
    public function isDeferred(array $mapping): bool
    {
        foreach ($this->deferrers as $deferrer) {
            if ($deferrer->supports($mapping)) {
                return true;
            }
        }

        return false;
    }
}
