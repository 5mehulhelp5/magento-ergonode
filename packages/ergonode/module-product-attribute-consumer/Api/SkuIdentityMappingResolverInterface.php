<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface SkuIdentityMappingResolverInterface
{
    /**
     * Validate the saved mapping for an assigned identity, including existing bindings.
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function resolve(): array;
}
