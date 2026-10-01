<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Api;

interface CategoryTreeStateProviderInterface
{
    /**
     * @param bool $activeOnly
     *
     * @return list<array{
     *     category_tree_id: int,
     *     tree_code: string,
     *     root_category_id: int,
     *     is_active: bool
     * }>
     */
    public function getTrees(bool $activeOnly = false): array;

    /**
     * Read a fresh canonical state, including mutations made earlier in this process.
     *
     * @param int $categoryTreeId
     *
     * @return array{
     *     tree: array{
     *         category_tree_id: int,
     *         tree_code: string,
     *         root_category_id: int,
     *         root_label: string,
     *         is_active: bool
     *     },
     *     source: list<array{
     *         identifier: string,
     *         label: string,
     *         parent_identifier: string|null,
     *         source_parent_identifier: string|null,
     *         sort_order: int,
     *         source_sort_order: int,
     *         magento_category_id: int|null,
     *         magento_label: string|null,
     *         active: bool
     *     }>,
     *     target: list<array{
     *         identifier: string,
     *         label: string,
     *         parent_identifier: string|null,
     *         sort_order: int,
     *         level: int,
     *         path: string,
     *         active: bool,
     *         category_code: string|null
     *     }>
     * }
     */
    public function getState(int $categoryTreeId): array;
}
