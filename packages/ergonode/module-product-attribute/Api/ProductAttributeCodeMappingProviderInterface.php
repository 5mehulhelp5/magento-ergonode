<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface ProductAttributeCodeMappingProviderInterface
{
    /**
     * Return complete Ergonode-to-Magento attribute-code mappings.
     *
     * @param string[] $ergonodeAttributeCodes
     * @return array<string, string>
     */
    public function getMagentoAttributeCodes(array $ergonodeAttributeCodes): array;
}
