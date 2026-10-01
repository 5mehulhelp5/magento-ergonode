<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Magento\Framework\DataObject\IdentityInterface;

class CategoryCacheIdentity implements IdentityInterface
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        private readonly array $tags
    ) {
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return $this->tags;
    }
}
