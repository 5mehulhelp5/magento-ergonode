<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

interface CategoryMappingSaveHandlerInterface
{
    /**
     * Save an accepted layout atomically with optional mapping extensions.
     *
     * @param int $categoryTreeId Tree whose accepted mappings and visibility are saved.
     * @param callable(): array{updated: int, unchanged: int, attribute_values: int} $saveLayout
     * @param array<string, int> $newMappings Category codes mapped to Magento IDs.
     * @return array{updated: int, unchanged: int, attribute_values: int}
     */
    public function save(int $categoryTreeId, callable $saveLayout, array $newMappings): array;
}
