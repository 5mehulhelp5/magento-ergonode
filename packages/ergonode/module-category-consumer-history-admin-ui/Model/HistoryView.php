<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Model;

/** Limits the viewer payload to changed categories and their historical context. */
class HistoryView
{
    /** @param array<string, mixed>|null $state @return array<string, mixed>|null */
    public function summary(?array $state, bool $changesOnly = true): ?array
    {
        if ($state === null) {
            return null;
        }
        $state['changes_only'] = $changesOnly;
        if (!$changesOnly) {
            return $state;
        }
        $snapshots = ['source' => [], 'target' => []];
        foreach (['source', 'target'] as $side) {
            foreach ($state[$side] as $item) {
                $snapshots[$side][(string)$item['identifier']][] = $item;
            }
        }
        $queue = [];
        foreach ($state['changes'] as $change) {
            $side = $change['entity_type'];
            $identifier = (string)$change['entity_identifier'];
            $queue[] = [$side, $identifier];
            foreach ([$change['before'], $change['after']] as $snapshot) {
                if ($snapshot !== null) {
                    $snapshots[$side][$identifier][] = $snapshot;
                }
            }
        }
        $selected = $this->selectContext($snapshots, $queue);
        foreach (['source', 'target'] as $side) {
            $state[$side] = array_values(array_filter(
                $state[$side],
                static fn (array $item): bool => isset($selected[$side][(string)$item['identifier']])
            ));
        }
        return $state;
    }

    /**
     * @param array<string, array<string, list<array<string, mixed>>>> $snapshots
     * @param list<array{string, string}> $queue
     * @return array<string, array<string, bool>>
     */
    private function selectContext(array $snapshots, array $queue): array
    {
        $selected = ['source' => [], 'target' => []];
        $length = count($queue);
        for ($index = 0; $index < $length; $index++) {
            [$side, $identifier] = $queue[$index];
            if (isset($selected[$side][$identifier])) {
                continue;
            }
            $selected[$side][$identifier] = true;
            foreach ($snapshots[$side][$identifier] ?? [] as $item) {
                foreach ($this->references($side, $item) as $reference) {
                    $queue[] = $reference;
                    $length++;
                }
            }
        }
        return $selected;
    }

    /** @param array<string, mixed> $item @return list<array{string, string}> */
    private function references(string $side, array $item): array
    {
        $references = [];
        $fields = $side === 'source'
            ? ['parent_identifier' => 'source', 'source_parent_identifier' => 'source',
                'magento_category_id' => 'target']
            : ['parent_identifier' => 'target', 'category_code' => 'source'];
        foreach ($fields as $field => $referenceSide) {
            if (isset($item[$field]) && (string)$item[$field] !== '') {
                $references[] = [$referenceSide, (string)$item[$field]];
            }
        }
        return $references;
    }
}
