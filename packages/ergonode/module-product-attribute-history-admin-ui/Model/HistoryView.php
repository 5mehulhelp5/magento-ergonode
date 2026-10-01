<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistoryAdminUi\Model;

/** Prepares historical option data for the read-only, progressively loaded viewer. */
class HistoryView
{
    /**
     * @param array<string, mixed>|null $state
     * @return array<string, mixed>|null
     */
    public function summary(?array $state, bool $changesOnly = false): ?array
    {
        if ($state !== null) {
            $state['changes_only'] = $changesOnly;
            if ($changesOnly) {
                $state = $this->changedAttributes($state);
            }
        }
        if ($state === null || !isset($state['options'])) {
            return $state;
        }
        $state['option_counts'] = ['source' => [], 'target' => []];
        foreach ($state['options'] as $side => $parents) {
            foreach ($parents as $parent => $options) {
                $state['option_counts'][$side][$parent] = $changesOnly ? 0 : count(array_filter(
                    $options,
                    static fn (array $option): bool => $side === 'target'
                        || empty($option['mapped_code']) || !$option['active']
                ));
                $state['options'][$side][$parent] = array_values(array_filter(
                    $options,
                    fn (array $option): bool => $this->isChanged($state['changes'], $side, (string)$parent, $option)
                ));
            }
        }
        return $state;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function changedAttributes(array $state): array
    {
        $codes = ['source' => [], 'target' => []];
        foreach ($state['changes'] as $change) {
            $side = $change['side'];
            $opposite = $side === 'source' ? 'target' : 'source';
            $isOption = ($change['entity'] ?? '') === 'option';
            $codes[$side][$isOption ? $change['attribute_code'] : $change['code']] = true;
            $linkedField = $isOption ? 'mapped_attribute_code' : 'mapped_code';
            foreach ([$change['before'], $change['after']] as $snapshot) {
                if (isset($snapshot[$linkedField])) {
                    $codes[$opposite][$snapshot[$linkedField]] = true;
                }
            }
        }
        foreach (['source', 'target'] as $side) {
            $opposite = $side === 'source' ? 'target' : 'source';
            foreach ($state[$side] as $item) {
                if (isset($codes[$side][$item['code']]) && isset($item['mapped_code'])) {
                    $codes[$opposite][$item['mapped_code']] = true;
                }
            }
        }
        foreach (['source', 'target'] as $side) {
            $state[$side] = array_values(array_filter(
                $state[$side],
                static fn (array $item): bool => isset($codes[$side][$item['code']])
            ));
            if (isset($state['options'])) {
                $state['options'][$side] = array_intersect_key($state['options'][$side], $codes[$side]);
            }
        }
        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, array<string, list<array<string, mixed>>>>|null
     */
    public function options(array $state, string $side, string $code): ?array
    {
        if (!isset($state['options'])) {
            return null;
        }
        $opposite = $side === 'source' ? 'target' : 'source';
        $linked = null;
        foreach ($state[$side] as $attribute) {
            if ($attribute['code'] === $code) {
                $linked = $attribute['mapped_code'];
            }
        }
        foreach ($state['changes'] as $change) {
            if (($change['entity'] ?? '') !== 'option' && $change['side'] === $side && $change['code'] === $code) {
                $linked = $linked ?: ($change['before']['mapped_code'] ?? null);
            }
        }
        return [
            $side => [$code => $state['options'][$side][$code] ?? []],
            $opposite => $linked === null ? [] : [$linked => $state['options'][$opposite][$linked] ?? []],
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return list<string>
     */
    public function search(array $state, string $side, string $query): array
    {
        $parents = [];
        foreach ($state['options'][$side] ?? [] as $parent => $options) {
            foreach ($options as $option) {
                if ($side === 'source' && !empty($option['mapped_code']) && $option['active']) {
                    continue;
                }
                $opposite = $side === 'source' ? 'target' : 'source';
                $linked = array_filter(
                    $state['options'][$opposite][$option['mapped_attribute_code'] ?? ''] ?? [],
                    static fn (array $item): bool => $item['code'] === $option['mapped_code']
                );
                $linkedOption = reset($linked);
                if (mb_stripos(implode(' ', [
                    $option['label'], $option['code'], $option['type'], $option['mapped_code'] ?? '',
                    $linkedOption['label'] ?? '',
                ]), $query) !== false) {
                    $parents[] = (string)$parent;
                    break;
                }
            }
        }
        return $parents;
    }

    /**
     * @param list<array<string, mixed>> $changes
     * @param array<string, mixed> $option
     */
    private function isChanged(array $changes, string $side, string $parent, array $option): bool
    {
        foreach ($changes as $change) {
            if (($change['entity'] ?? '') !== 'option') {
                continue;
            }
            if ($change['side'] === $side && $change['attribute_code'] === $parent
                && $change['code'] === $option['code']) {
                return true;
            }
            foreach ([$change['before'], $change['after']] as $snapshot) {
                if ($change['side'] !== $side && ($snapshot['mapped_attribute_code'] ?? null) === $parent
                    && ($snapshot['mapped_code'] ?? null) === $option['code']) {
                    return true;
                }
            }
        }
        return false;
    }
}
