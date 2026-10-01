<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Model;

class TreeDiffer
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array{
     *     changes: list<array{
     *         entity_type: string,
     *         entity_identifier: string,
     *         category_code: string|null,
     *         actions: list<string>,
     *         before: array<string, int|string|bool|null>|null,
     *         after: array<string, int|string|bool|null>|null
     *     }>,
     *     summary: array<string, int>
     * }
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];
        $summary = ['changes' => 0, 'categories' => 0];
        $categoryCodes = [];

        foreach (['source', 'target'] as $entityType) {
            $beforeItems = $this->index(is_array($before[$entityType] ?? null) ? $before[$entityType] : []);
            $afterItems = $this->index(is_array($after[$entityType] ?? null) ? $after[$entityType] : []);
            $identifiers = array_values(array_unique([...array_keys($beforeItems), ...array_keys($afterItems)]));
            sort($identifiers);

            foreach ($identifiers as $identifier) {
                $beforeItem = $beforeItems[$identifier] ?? null;
                $afterItem = $afterItems[$identifier] ?? null;
                $actions = $this->actions($entityType, $beforeItem, $afterItem);
                if ($actions === []) {
                    continue;
                }
                $categoryCode = $entityType === 'source'
                    ? $identifier
                    : $this->nullableString($afterItem['category_code'] ?? $beforeItem['category_code'] ?? null);
                $changes[] = [
                    'entity_type' => $entityType,
                    'entity_identifier' => $identifier,
                    'category_code' => $categoryCode,
                    'actions' => $actions,
                    'before' => $beforeItem,
                    'after' => $afterItem,
                ];
                $summary['changes']++;
                if ($categoryCode !== null) {
                    $categoryCodes[$categoryCode] = true;
                }
                foreach ($actions as $action) {
                    $summary[$action] = ($summary[$action] ?? 0) + 1;
                }
            }
        }
        $summary['categories'] = count($categoryCodes);

        return ['changes' => $changes, 'summary' => $summary];
    }

    /**
     * @param array<array-key, array<string, int|string|bool|null>> $items
     * @return array<string, array<string, int|string|bool|null>>
     */
    private function index(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $identifier = trim((string)($item['identifier'] ?? ''));
            if ($identifier !== '') {
                $indexed[$identifier] = $item;
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, int|string|bool|null>|null $before
     * @param array<string, int|string|bool|null>|null $after
     * @return list<string>
     */
    private function actions(string $entityType, ?array $before, ?array $after): array
    {
        if ($before === null) {
            if ($after === null) {
                return [];
            }
            $actions = ['created'];
            if ($entityType === 'source' && (int)($after['magento_category_id'] ?? 0) > 0) {
                $actions[] = 'connected';
            }

            return $actions;
        }
        if ($after === null) {
            return ['deleted'];
        }

        $actions = [];
        if (($before['parent_identifier'] ?? null) !== ($after['parent_identifier'] ?? null)) {
            $actions[] = 'moved';
        }
        if ((int)($before['sort_order'] ?? 0) !== (int)($after['sort_order'] ?? 0)) {
            $actions[] = 'reordered';
        }
        if ((string)($before['label'] ?? '') !== (string)($after['label'] ?? '')) {
            $actions[] = 'renamed';
        }
        if ((bool)($before['active'] ?? true) !== (bool)($after['active'] ?? true)) {
            $actions[] = !empty($after['active']) ? 'included' : 'excluded';
        }
        if ($entityType !== 'source') {
            return $actions;
        }
        if (($before['source_parent_identifier'] ?? null) !== ($after['source_parent_identifier'] ?? null)
            && !in_array('moved', $actions, true)
        ) {
            $actions[] = 'source_moved';
        }
        if ((int)($before['source_sort_order'] ?? 0) !== (int)($after['source_sort_order'] ?? 0)
            && !in_array('reordered', $actions, true)
        ) {
            $actions[] = 'source_reordered';
        }
        $previousTarget = (int)($before['magento_category_id'] ?? 0);
        $currentTarget = (int)($after['magento_category_id'] ?? 0);
        if ($previousTarget !== $currentTarget) {
            $actions[] = match (true) {
                $previousTarget <= 0 => 'connected',
                $currentTarget <= 0 => 'disconnected',
                default => 'reconnected',
            };
        }

        return $actions;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
