<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Model\ManualTree;

use Magento\Framework\Exception\LocalizedException;

class CategoryTreePayloadBuilder
{
    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, string> $idsByCode
     * @return array<int, array{category_id: string, children: array}>
     */
    public function build(array $items, array $idsByCode): array
    {
        $normalized = [];
        foreach ($items as $index => $item) {
            $code = trim((string)($item['code'] ?? ''));
            if ($code === '' || isset($normalized[$code])) {
                continue;
            }
            $parent = trim((string)($item['parent_code'] ?? ''));
            $parent = $parent !== '' ? $parent : null;
            if ($parent === $code) {
                throw new LocalizedException(__('Category "%1" cannot be its own parent.', $code));
            }
            $normalized[$code] = [
                'code' => $code,
                'parent' => $parent,
                'sort' => max(0, (int)($item['sort_order'] ?? $index)),
                'index' => $index,
            ];
        }
        $checked = [];
        foreach ($normalized as $item) {
            if ($item['parent'] !== null && !isset($normalized[$item['parent']])) {
                throw new LocalizedException(__('Parent category "%1" is missing from the tree.', $item['parent']));
            }
            if (!isset($idsByCode[$item['code']])) {
                throw new LocalizedException(__('Ergonode category "%1" has no REST identifier.', $item['code']));
            }
            $this->assertNoCycle((string)$item['code'], $normalized, $checked);
        }

        $byParent = [];
        foreach ($normalized as $item) {
            $byParent[$item['parent'] ?? ''][] = $item;
        }

        return $this->childrenOf(null, $byParent, $idsByCode);
    }

    /**
     * @param array<int, array{category_id: string, children: array}> $nodes
     * @param array<string, string> $idsByCode
     * @return array<int, array{code: string, parent_code: string|null}>
     */
    public function snapshotLayout(array $nodes, array $idsByCode): array
    {
        return iterator_to_array($this->flatten($nodes, array_flip($idsByCode), null), false);
    }

    /**
     * @param array<int, array{category_id: string, children: array}> $nodes
     * @param array<string, string> $codesById
     * @return iterable<array{code: string, parent_code: string|null}>
     */
    private function flatten(array $nodes, array $codesById, ?string $parent): iterable
    {
        foreach ($nodes as $node) {
            $code = (string)$codesById[$node['category_id']];
            yield ['code' => $code, 'parent_code' => $parent];
            yield from $this->flatten($node['children'], $codesById, $code);
        }
    }

    /**
     * @param array<string, array{code: string, parent: string|null, sort: int, index: int}> $items
     * @param array<string, bool> $checked
     */
    private function assertNoCycle(string $code, array $items, array &$checked): void
    {
        if (isset($checked[$code])) {
            return;
        }
        $visited = [$code => true];
        $parent = $items[$code]['parent'];
        while ($parent !== null && !isset($checked[$parent])) {
            if (isset($visited[$parent])) {
                throw new LocalizedException(__('Category tree contains a cycle near "%1".', $code));
            }
            $visited[$parent] = true;
            $parent = $items[$parent]['parent'] ?? null;
        }
        $checked += $visited;
    }

    /**
     * @param array<string, array<int, array{code: string, parent: string|null, sort: int, index: int}>> $byParent
     * @param array<string, string> $idsByCode
     * @return array<int, array{category_id: string, children: array}>
     */
    private function childrenOf(?string $parent, array $byParent, array $idsByCode): array
    {
        $children = $byParent[$parent ?? ''] ?? [];
        usort(
            $children,
            static fn (array $a, array $b): int => [$a['sort'], $a['index']] <=> [$b['sort'], $b['index']]
        );

        return array_map(fn (array $item): array => [
            'category_id' => $idsByCode[$item['code']],
            'children' => $this->childrenOf($item['code'], $byParent, $idsByCode),
        ], $children);
    }
}
