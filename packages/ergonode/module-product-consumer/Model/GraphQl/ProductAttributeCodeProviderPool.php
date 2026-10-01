<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\GraphQl;

use Ergonode\ProductConsumer\Api\ProductAttributeCodeProviderInterface;

class ProductAttributeCodeProviderPool
{
    /** @param ProductAttributeCodeProviderInterface[] $providers */
    public function __construct(private readonly array $providers = [])
    {
    }

    /** @return string[] */
    public function getAttributeCodes(): array
    {
        $codes = [];
        foreach ($this->providers as $provider) {
            $codes = [...$codes, ...$provider->getAttributeCodes()];
        }

        return array_values(array_unique(array_filter(array_map('trim', $codes))));
    }
}
