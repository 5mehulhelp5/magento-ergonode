<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

interface ProductRelationStateInterface
{
    /**
     * @return string[]
     */
    public function getBindingCodes(): array;

    /**
     * @return string[]
     */
    public function getVariantSkus(): array;

    /**
     * @return array<string, int>
     */
    public function getGroupedChildren(): array;
}
