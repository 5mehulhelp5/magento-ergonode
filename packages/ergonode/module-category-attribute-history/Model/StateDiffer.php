<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model;

class StateDiffer
{
    /**
     * @param array{source: list<array<string, mixed>>, target: list<array<string, mixed>>} $before
     * @param array{source: list<array<string, mixed>>, target: list<array<string, mixed>>} $after
     * @return list<array{
     *     side: string, code: string, actions: list<string>,
     *     before: array<string, mixed>|null, after: array<string, mixed>|null
     * }>
     */
    public function compare(array $before, array $after): array
    {
        $changes = [];
        foreach (['source', 'target'] as $side) {
            $previous = array_column($before[$side], null, 'code');
            $current = array_column($after[$side], null, 'code');
            $codes = array_unique([...array_keys($previous), ...array_keys($current)]);
            sort($codes);
            foreach ($codes as $code) {
                $left = $previous[$code] ?? null;
                $right = $current[$code] ?? null;
                $actions = $this->actions($left, $right);
                if ($actions !== []) {
                    $changes[] = [
                        'side' => $side,
                        'code' => (string)$code,
                        'actions' => $actions,
                        'before' => $left,
                        'after' => $right,
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @return list<string>
     */
    private function actions(?array $before, ?array $after): array
    {
        if ($before === null) {
            return ['created'];
        }
        if ($after === null) {
            return ['deleted'];
        }
        $actions = [];
        foreach (['label' => 'renamed', 'type' => 'type_changed', 'scope' => 'scope_changed'] as $field => $action) {
            if ($before[$field] !== $after[$field]) {
                $actions[] = $action;
            }
        }
        if ($before['active'] !== $after['active']) {
            $actions[] = $after['active'] ? 'included' : 'excluded';
        }
        if ($before['mapped_code'] !== $after['mapped_code']) {
            $actions[] = match (true) {
                $after['mapped_code'] === null => 'disconnected',
                $before['mapped_code'] === null => 'connected',
                default => 'reconnected',
            };
        } elseif (($before['is_draft'] ?? false) !== ($after['is_draft'] ?? false)) {
            $actions[] = ($after['is_draft'] ?? false) ? 'draft_added' : 'draft_removed';
        }

        return $actions;
    }
}
