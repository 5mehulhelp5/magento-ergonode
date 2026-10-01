<?php

declare(strict_types=1);

namespace Ergonode\Product\Api;

interface ProductCatalogInterface
{
    /**
     * @param string $search
     * @param int $page
     * @param int $pageSize
     * @param array<string, mixed> $criteria Column filters plus sort and direction.
     * @return array{total: int, page: int, items: list<array{
     *     product_id: int, sku: string, ergonode_sku: string, name: string,
     *     thumbnail: string, attribute_set_id: int, attribute_set_name: string, type_id: string
     * }>, filter_options: array{attribute_set_id: list<array{value: string, label: string}>,
     *     type_id: list<array{value: string, label: string}>}}
     */
    public function getPage(string $search, int $page, int $pageSize, array $criteria = []): array;

    /**
     * Snapshot the matching selection in product-ID order before browser batching.
     * @param string $search
     * @param int[] $selected
     * @param int[] $excluded
     * @param bool $all
     * @param array<string, mixed> $criteria Same column filters as the grid.
     * @return int[]
     */
    public function getIds(string $search, array $selected, array $excluded, bool $all, array $criteria = []): array;
}
