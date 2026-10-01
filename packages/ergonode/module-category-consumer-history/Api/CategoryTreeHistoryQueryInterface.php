<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Api;

interface CategoryTreeHistoryQueryInterface
{
    /**
     * @return list<array{
     *     category_tree_id: int,
     *     tree_code: string,
     *     root_category_id: int,
     *     is_active: bool
     * }>
     */
    public function getTrees(): array;

    /**
     * @param int $categoryTreeId
     * @param int $limit
     *
     * @return list<array{
     *     operation_id: int,
     *     change_set_id: int,
     *     operation_code: string,
     *     origin: string,
     *     mode: string,
     *     status: string,
     *     actor_name: string|null,
     *     started_at: string,
     *     finished_at: string|null,
     *     summary: array<string, int>,
     *     operation_summary: array<string, int|string|bool|null>,
     *     change_count: int
     * }>
     */
    public function getOperations(int $categoryTreeId, int $limit = 50): array;

    /**
     * @param int $categoryTreeId
     * @param int $limit
     * @param int|null $beforeOperationId
     *
     * @return array{
     *     items: list<array{
     *         operation_id: int,
     *         change_set_id: int,
     *         operation_code: string,
     *         origin: string,
     *         mode: string,
     *         status: string,
     *         actor_name: string|null,
     *         started_at: string,
     *         finished_at: string|null,
     *         summary: array<string, int>,
     *         operation_summary: array<string, int|string|bool|null>,
     *         change_count: int
     *     }>,
     *     total: int,
     *     page_size: int,
     *     has_more: bool,
     *     next_before_id: int|null
     * }
     */
    public function getOperationsPage(
        int $categoryTreeId,
        int $limit = 10,
        ?int $beforeOperationId = null
    ): array;

    /**
     * @param int $categoryTreeId
     * @param int|null $operationId
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
     *     }>,
     *     operation: array{
     *         operation_id: int,
     *         operation_code: string,
     *         origin: string,
     *         mode: string,
     *         status: string,
     *         actor_name: string|null,
     *         started_at: string,
     *         finished_at: string|null
     *     }|null,
     *     changes: list<array{
     *         change_id: int,
     *         entity_type: string,
     *         entity_identifier: string,
     *         category_code: string|null,
     *         actions: list<string>,
     *         before: array<string, int|string|bool|null>|null,
     *         after: array<string, int|string|bool|null>|null
     *     }>
     * }
     */
    public function getState(int $categoryTreeId, ?int $operationId = null): array;
}
