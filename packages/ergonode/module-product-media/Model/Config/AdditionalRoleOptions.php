<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Config;

use Ergonode\ProductMedia\Api\AdditionalRoleOptionsInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;

class AdditionalRoleOptions implements AdditionalRoleOptionsInterface
{
    public function __construct(
        private readonly ImageRolesInterface $roles,
        private readonly CompleteMappingProviderInterface $mappings
    ) {
    }
    public function getOptions(): array
    {
        $excluded = ['image', 'small_image', 'thumbnail'];
        foreach ($this->mappings->getMappings() as $mapping) {
            $excluded[] = $mapping['magento_attribute_code'];
        }
        return array_diff_key($this->roles->getOptions(), array_fill_keys($excluded, true));
    }
}
