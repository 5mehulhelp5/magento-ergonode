<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategoryMappingProviderInterface
{
    /**
     * @param int[] $categoryIds
     * @return array<int, string> Ergonode codes keyed by Magento category ID.
     * @throws LocalizedException
     */
    public function getCategoryCodesByMagentoIds(array $categoryIds): array;

    /**
     * Return complete Magento category mappings grouped by Ergonode category code.
     *
     * @param string[] $categoryCodes
     * @param int|null $rootCategoryId Limit mappings to a Magento root category when provided.
     * @return array<string, int[]>
     */
    public function getMagentoCategoryIdsByErgonodeCodes(
        array $categoryCodes,
        ?int $rootCategoryId = null
    ): array;
}
