<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface SkuIdentityMappingProviderInterface
{
    /**
     * Read the complete SKU mapping even when the mode for new bindings changes.
     *
     * @return array{mapping_id: int, ergonode_attribute_code: string, magento_attribute_code: string,
     *     ergonode_type: string, magento_type: string, option_ids: array<string, int>}|null
     */
    public function getMapping(): ?array;
}
