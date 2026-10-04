<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Cache;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;

class ProductCacheIdentity implements IdentityInterface
{
    /** @param list<int> $ids */
    public function __construct(private readonly array $ids)
    {
    }

    public function getIdentities(): array
    {
        return array_map(static fn(int $id): string => Product::CACHE_TAG . '_' . $id, $this->ids);
    }
}
