<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryTreeReadinessProviderInterface
{
    /**
     * Return active category-tree mappings, optionally limited to an Ergonode tree code.
     *
     * @param string|null $treeCode
     * @return array<int, array{
     *     category_tree_id: int,
     *     tree_code: string,
     *     root_category_id: int,
     *     root_exists: bool
     * }>
     */
    public function getActiveTrees(?string $treeCode = null): array;
}
