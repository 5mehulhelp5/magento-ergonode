<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategoryLayoutSaverInterface
{
    /**
     * @param int $categoryTreeId
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array<string, mixed>> $visibility
     * @return array{updated: int, unchanged: int, attribute_values: int}
     * @throws LocalizedException
     */
    public function save(int $categoryTreeId, array $items, array $visibility = []): array;
}
