<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategoryCreationContextProviderInterface
{
    /**
     * @param int $categoryId
     * @return array{
     *     category_tree_id: int,
     *     root_category_id: int,
     *     mapped_code: string|null,
     *     pending_code: string|null,
     *     category: array{
     *         id: int,
     *         label: string,
     *         url_key: string,
     *         position: int,
     *         path_ids: int[],
     *         path_labels: string[]
     *     },
     *     items: array<int, array{
     *         code: string,
     *         label: string,
     *         parent_code: string|null,
     *         sort_order: int,
     *         magento_category_id: int|null
     *     }>
     * }|null
     * @throws LocalizedException
     */
    public function getForMagentoCategory(int $categoryId): ?array;
}
