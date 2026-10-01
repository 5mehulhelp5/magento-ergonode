<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistoryAdminUi\Model;

/** Selects the attribute pairs needed to present recorded changes. */
class HistoryView
{
    /** @param array<string, mixed>|null $state @return array<string, mixed>|null */
    public function summary(?array $state, bool $changesOnly = true): ?array
    {
        if ($state === null) {
            return null;
        }
        $state['changes_only'] = $changesOnly;
        $state['lacks_draft_status'] = count(array_filter(
            array_merge($state['source'], $state['target']),
            static fn (array $item): bool => !array_key_exists('is_draft', $item)
        )) > 0;
        if (!$changesOnly) {
            return $state;
        }
        $codes = ['source' => [], 'target' => []];
        foreach ($state['changes'] as $change) {
            $side = $change['side'];
            $opposite = $side === 'source' ? 'target' : 'source';
            $codes[$side][$change['code']] = true;
            foreach ([$change['before'], $change['after']] as $snapshot) {
                if (isset($snapshot['mapped_code'])) {
                    $codes[$opposite][$snapshot['mapped_code']] = true;
                }
            }
        }
        foreach (['source', 'target'] as $side) {
            $opposite = $side === 'source' ? 'target' : 'source';
            $state[$side] = array_values(array_filter(
                $state[$side],
                static fn (array $item): bool => isset($codes[$side][$item['code']])
                    || isset($codes[$opposite][$item['mapped_code'] ?? ''])
            ));
        }
        return $state;
    }
}
