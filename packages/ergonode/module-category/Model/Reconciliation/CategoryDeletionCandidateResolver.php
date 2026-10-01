<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Reconciliation;

use function sprintf;
use function uasort;

class CategoryDeletionCandidateResolver
{
    /**
     * @param array<string, array<string, mixed>> $sources
     * @param array<int, array<string, mixed>> $magento
     * @param array<string, int> $databaseMappings
     * @param array<int, true> $consumed
     * @param array<int, true> $protected
     * @return array{candidates: int[], conflicts: string[]}
     */
    public function resolve(
        int $rootCategoryId,
        array $sources,
        array $magento,
        array $databaseMappings,
        array $consumed,
        array $protected
    ): array {
        $eligible = [];
        foreach ($databaseMappings as $code => $categoryId) {
            $categoryId = (int)$categoryId;
            if (isset($sources[(string)$code])
                || $categoryId === $rootCategoryId
                || !isset($magento[$categoryId])
                || isset($consumed[$categoryId])
                || isset($protected[$categoryId])
            ) {
                continue;
            }
            $eligible[$categoryId] = (int)($magento[$categoryId]['level'] ?? 0);
        }

        $children = [];
        foreach ($magento as $categoryId => $category) {
            $children[(int)($category['parent_id'] ?? 0)][] = (int)$categoryId;
        }
        $conflicts = [];
        $candidates = [];
        foreach ($eligible as $categoryId => $level) {
            if (!$this->ownsCompleteSubtree($categoryId, $children, $eligible)) {
                $conflicts[] = sprintf(
                    'Mapped Magento category #%d is missing in Ergonode but contains '
                    . 'an unmanaged or excluded descendant.',
                    $categoryId
                );
                continue;
            }
            $candidates[$categoryId] = $level;
        }
        uasort($candidates, static fn (int $first, int $second): int => $second <=> $first);

        return [
            'candidates' => array_map('intval', array_keys($candidates)),
            'conflicts' => $conflicts,
        ];
    }

    /** @param array<int, int[]> $children @param array<int, int> $eligible */
    private function ownsCompleteSubtree(int $categoryId, array $children, array $eligible): bool
    {
        foreach ($children[$categoryId] ?? [] as $childId) {
            if (!isset($eligible[$childId]) || !$this->ownsCompleteSubtree($childId, $children, $eligible)) {
                return false;
            }
        }

        return true;
    }
}
