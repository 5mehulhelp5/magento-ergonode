<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

interface CategoryFormContextProviderInterface
{
    /**
     * @param int $categoryId
     * @return array{
     *     category_tree_id: int,
     *     root_category_id: int,
     *     ergonode_category_code: string|null
     * }|null
     */
    public function getForMagentoCategory(int $categoryId): ?array;
}
