<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model;

use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;

use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryConsumerHistory\Api\CategoryTreeHistoryQueryInterface;
use Ergonode\CategoryConsumerHistory\Model\ResourceModel\HistoryReader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Throwable;

class CategoryTreeHistoryQuery implements CategoryTreeHistoryQueryInterface
{
    public function __construct(
        private readonly CategoryTreeStateProviderInterface $stateProvider,
        private readonly HistoryReader $historyReader,
        private readonly Json $json,
        private readonly CategorySynchronizationLock $lock
    ) {
    }

    public function getTrees(): array
    {
        return $this->stateProvider->getTrees();
    }

    public function getOperations(int $categoryTreeId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return array_map(
            fn (array $row): array => $this->normalizeOperation($row),
            $this->historyReader->getOperations($categoryTreeId, $limit)
        );
    }

    public function getOperationsPage(
        int $categoryTreeId,
        int $limit = 10,
        ?int $beforeOperationId = null
    ): array {
        $limit = max(1, min(200, $limit));
        $rows = $this->historyReader->getOperations($categoryTreeId, $limit + 1, $beforeOperationId);
        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(
            fn (array $row): array => $this->normalizeOperation($row),
            $rows
        );
        $lastItem = $items !== [] ? $items[array_key_last($items)] : null;

        return [
            'items' => $items,
            'total' => $this->historyReader->countOperations($categoryTreeId),
            'page_size' => $limit,
            'has_more' => $hasMore,
            'next_before_id' => $hasMore && $lastItem !== null ? $lastItem['operation_id'] : null,
        ];
    }

    public function getState(int $categoryTreeId, ?int $operationId = null): array
    {
        return $this->lock->execute(fn (): array => $this->reconstruct($categoryTreeId, $operationId));
    }

    /** @return array<string, mixed> */
    private function reconstruct(int $categoryTreeId, ?int $operationId): array
    {
        $state = $this->stateProvider->getState($categoryTreeId);
        if ($operationId === null) {
            return $state + ['operation' => null, 'changes' => []];
        }

        $changeSet = $this->historyReader->getChangeSet($categoryTreeId, $operationId);
        $operation = $this->historyReader->getOperation($operationId);
        if ($changeSet === null || $operation === null) {
            throw new LocalizedException(__('The selected category-tree history operation no longer exists.'));
        }

        $indexed = [
            'source' => $this->index($state['source']),
            'target' => $this->index($state['target']),
        ];
        foreach ($this->historyReader->getChangesAfter($categoryTreeId, $operationId) as $change) {
            $this->restoreState($indexed, $change, 'before_state');
        }
        $changes = $this->historyReader->getChanges((int)$changeSet['change_set_id']);
        // The selected operation is authoritative for its own changed entities, even across older gaps.
        foreach ($changes as $change) {
            $this->restoreState($indexed, $change, 'after_state');
        }
        $this->synchronizeMappingDecorations($indexed);
        $tree = $state['tree'];
        $tree['tree_code'] = (string)$changeSet['tree_code'];
        $tree['root_category_id'] = (int)$changeSet['root_category_id'];
        $tree['root_label'] = (string)$changeSet['root_label'];

        return [
            'tree' => $tree,
            'source' => $this->sortItems(array_values($indexed['source'])),
            'target' => $this->sortItems(array_values($indexed['target'])),
            'operation' => [
                'operation_id' => (int)$operation['operation_id'],
                'operation_code' => (string)$operation['operation_code'],
                'origin' => (string)$operation['origin'],
                'mode' => (string)$operation['operation_code'] === 'synchronize_data' ? 'categoryStream' : 'treeStream',
                'status' => (string)$operation['status'],
                'actor_name' => $this->nullableString($operation['actor_name'] ?? null),
                'started_at' => (string)$operation['started_at'],
                'finished_at' => $this->nullableString($operation['finished_at'] ?? null),
            ],
            'changes' => array_map(
                fn (array $change): array => $this->normalizeChange($change),
                $changes
            ),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
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
     * }
     */
    private function normalizeOperation(array $row): array
    {
        $summary = $this->decodeObject($row['set_summary_json'] ?? null);

        return [
            'operation_id' => (int)$row['operation_id'],
            'change_set_id' => (int)$row['change_set_id'],
            'operation_code' => (string)$row['operation_code'],
            'origin' => (string)$row['origin'],
            'mode' => (string)$row['operation_code'] === 'synchronize_data' ? 'categoryStream' : 'treeStream',
            'status' => (string)$row['status'],
            'actor_name' => $this->nullableString($row['actor_name'] ?? null),
            'started_at' => (string)$row['started_at'],
            'finished_at' => $this->nullableString($row['finished_at'] ?? null),
            'summary' => $this->integerSummary($summary),
            'operation_summary' => $this->decodeObject($row['operation_summary_json'] ?? null),
            'change_count' => (int)($summary['changes'] ?? 0),
        ];
    }

    /**
     * @param list<array<string, int|string|bool|null>> $items
     * @return array<string, array<string, int|string|bool|null>>
     */
    private function index(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $indexed[(string)$item['identifier']] = $item;
        }

        return $indexed;
    }

    /**
     * @param array<string, array<string, array<string, int|string|bool|null>>> $indexed
     * @param array<string, mixed> $change
     * @param 'before_state'|'after_state' $stateField
     */
    private function restoreState(array &$indexed, array $change, string $stateField): void
    {
        $entityType = (string)$change['entity_type'];
        if (!isset($indexed[$entityType])) {
            return;
        }
        $identifier = (string)$change['entity_identifier'];
        $snapshot = $this->decodeNullableObject($change[$stateField] ?? null);
        if ($snapshot === null) {
            unset($indexed[$entityType][$identifier]);

            return;
        }
        $indexed[$entityType][$identifier] = $snapshot;
    }

    /** @param array<string, array<string, array<string, int|string|bool|null>>> $indexed */
    private function synchronizeMappingDecorations(array &$indexed): void
    {
        $targetLabels = [];
        foreach ($indexed['target'] as $identifier => $target) {
            $targetLabels[(int)$identifier] = (string)($target['label'] ?? '');
            $indexed['target'][$identifier]['category_code'] = null;
        }
        foreach ($indexed['source'] as $identifier => &$source) {
            $magentoCategoryId = (int)($source['magento_category_id'] ?? 0);
            $source['magento_label'] = $magentoCategoryId > 0
                ? ($targetLabels[$magentoCategoryId] ?? null)
                : null;
            if ($magentoCategoryId > 0 && isset($indexed['target'][(string)$magentoCategoryId])) {
                $indexed['target'][(string)$magentoCategoryId]['category_code'] = $identifier;
            }
        }
        unset($source);
    }

    /**
     * @param array<string, mixed> $change
     * @return array{
     *     change_id: int,
     *     entity_type: string,
     *     entity_identifier: string,
     *     category_code: string|null,
     *     actions: list<string>,
     *     before: array<string, int|string|bool|null>|null,
     *     after: array<string, int|string|bool|null>|null
     * }
     */
    private function normalizeChange(array $change): array
    {
        return [
            'change_id' => (int)$change['change_id'],
            'entity_type' => (string)$change['entity_type'],
            'entity_identifier' => (string)$change['entity_identifier'],
            'category_code' => $this->nullableString($change['category_code'] ?? null),
            'actions' => $this->decodeList($change['actions_json'] ?? null),
            'before' => $this->decodeNullableObject($change['before_state'] ?? null),
            'after' => $this->decodeNullableObject($change['after_state'] ?? null),
        ];
    }

    /**
     * @param list<array<string, int|string|bool|null>> $items
     * @return list<array<string, int|string|bool|null>>
     */
    private function sortItems(array $items): array
    {
        usort($items, static function (array $left, array $right): int {
            $parentComparison = strcmp(
                (string)($left['parent_identifier'] ?? ''),
                (string)($right['parent_identifier'] ?? '')
            );
            if ($parentComparison !== 0) {
                return $parentComparison;
            }
            $sortComparison = (int)($left['sort_order'] ?? 0) <=> (int)($right['sort_order'] ?? 0);

            return $sortComparison !== 0
                ? $sortComparison
                : strcmp((string)$left['identifier'], (string)$right['identifier']);
        });

        return $items;
    }

    /** @return array<string, int|string|bool|null> */
    private function decodeObject(mixed $value): array
    {
        $decoded = $this->decode($value);

        if (!is_array($decoded)) {
            return [];
        }
        $result = [];
        foreach ($decoded as $key => $item) {
            if (is_int($item) || is_string($item) || is_bool($item) || $item === null) {
                $result[(string)$key] = $item;
            }
        }

        return $result;
    }

    /** @return array<string, int|string|bool|null>|null */
    private function decodeNullableObject(mixed $value): ?array
    {
        return $value === null ? null : $this->decodeObject($value);
    }

    /** @return list<string> */
    private function decodeList(mixed $value): array
    {
        $decoded = $this->decode($value);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $item) {
            if (is_string($item) || is_int($item)) {
                $result[] = (string)$item;
            }
        }

        return $result;
    }

    /**
     * @param array<string, int|string|bool|null> $summary
     * @return array<string, int>
     */
    private function integerSummary(array $summary): array
    {
        return array_map('intval', $summary);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
    private function decode(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return $this->json->unserialize($value);
        } catch (Throwable) {
            return null;
        }
    }
}
