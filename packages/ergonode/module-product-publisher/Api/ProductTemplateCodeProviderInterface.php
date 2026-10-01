<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

interface ProductTemplateCodeProviderInterface
{
    /**
     * Return Ergonode template codes keyed by Magento product attribute-set ID.
     *
     * Omitted attribute-set IDs are unavailable for product publication.
     *
     * @param int[] $attributeSetIds
     * @return array<int, string>
     */
    public function getTemplateCodesByAttributeSetIds(array $attributeSetIds): array;
}
