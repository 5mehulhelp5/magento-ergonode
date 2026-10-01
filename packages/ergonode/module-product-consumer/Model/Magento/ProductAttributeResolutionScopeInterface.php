<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

interface ProductAttributeResolutionScopeInterface
{
    /** Release values retained for a single mapping operation, including failed operations. */
    public function resetResolutionScope(): void;
}
