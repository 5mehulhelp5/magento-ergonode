<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Model;

use Ergonode\Category\Api\CategoryMappingProviderInterface;
use Ergonode\ProductConsumer\Exception\DependencyUnavailableException;

class CategoryIdsResolver
{
    public function __construct(private readonly CategoryMappingProviderInterface $categoryMappingProvider)
    {
    }

    /** @param string[] $categoryCodes @return int[] */
    public function resolve(array $categoryCodes): array
    {
        $categoryCodes = array_values(array_unique(array_filter(array_map('trim', $categoryCodes))));
        if ($categoryCodes === []) {
            return [];
        }
        $resolved = $this->categoryMappingProvider->getMagentoCategoryIdsByErgonodeCodes($categoryCodes);
        $missing = array_values(array_filter(
            $categoryCodes,
            static fn (string $code): bool => ($resolved[$code] ?? []) === []
        ));
        if ($missing !== []) {
            throw new DependencyUnavailableException(__(
                'Ergonode product categories are not mapped to Magento yet: %1.',
                implode(', ', $missing)
            ));
        }
        $ids = [];
        foreach ($resolved as $categoryIds) {
            $ids = [...$ids, ...$categoryIds];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }
}
