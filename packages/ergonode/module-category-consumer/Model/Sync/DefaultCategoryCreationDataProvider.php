<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryCreationDataProviderInterface;

class DefaultCategoryCreationDataProvider implements CategoryCreationDataProviderInterface
{
    public function __construct(
        private readonly CategoryCreationConfigurationProviderInterface $configurationProvider
    ) {
    }

    public function get(string $categoryCode): array
    {
        return [
            'values' => $this->configurationProvider->get()['fixed_values'],
            'entity' => null,
        ];
    }
}
