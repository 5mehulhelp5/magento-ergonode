<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;

class DefaultCategoryCreationConfigurationProvider implements CategoryCreationConfigurationProviderInterface
{
    public function get(): array
    {
        return [
            'attributes_enabled' => false,
            'fixed_values' => ['is_active' => 1, 'include_in_menu' => 1],
            'mapped_attribute_codes' => [],
        ];
    }
}
