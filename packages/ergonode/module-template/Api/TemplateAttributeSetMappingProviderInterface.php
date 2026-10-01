<?php

declare(strict_types=1);

namespace Ergonode\Template\Api;

use Magento\Framework\Exception\LocalizedException;

interface TemplateAttributeSetMappingProviderInterface
{
    /**
     * @param int[] $attributeSetIds
     * @return array<int, string> Ergonode template codes keyed by Magento attribute-set ID.
     * @throws LocalizedException
     */
    public function getTemplateCodesByAttributeSetIds(array $attributeSetIds): array;
}
