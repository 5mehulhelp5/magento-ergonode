<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Reconciliation;

/** Effective protection shared by structure reconciliation and category data writes. */
class CategoryExclusionResolver
{
    /**
     * @param array<string, array<string, mixed>> $sources Indexed source nodes with parent_code and active.
     * @param array<int, array<string, mixed>> $targets Indexed Magento nodes with parent_id and active.
     * @param array<string, int> $mappings Accepted identities whose source and target exist.
     * @return array{sources: array<string, true>, targets: array<int, true>}
     */
    public function resolve(int $rootCategoryId, array $sources, array $targets, array $mappings): array
    {
        $sourceChildren = [];
        $targetChildren = [];
        $pendingSources = [];
        $pendingTargets = [];
        foreach ($sources as $code => $source) {
            $sourceChildren[(string)($source['parent_code'] ?? '')][] = (string)$code;
            if (empty($source['active'])) {
                $pendingSources[] = (string)$code;
            }
        }
        foreach ($targets as $id => $target) {
            $targetChildren[(int)($target['parent_id'] ?? 0)][] = $id;
            if (empty($target['active'])) {
                $pendingTargets[] = $id;
            }
        }
        $mappedSources = array_flip($mappings);
        $excluded = [];
        $protected = [];
        while ($pendingSources !== [] || $pendingTargets !== []) {
            if ($pendingSources !== []) {
                $code = array_pop($pendingSources);
                if (isset($excluded[$code])) {
                    continue;
                }
                $excluded[$code] = true;
                array_push($pendingSources, ...($sourceChildren[$code] ?? []));
                if (isset($mappings[$code])) {
                    $pendingTargets[] = $mappings[$code];
                }
                continue;
            }
            $categoryId = array_pop($pendingTargets);
            if (isset($protected[$categoryId])) {
                continue;
            }
            $protected[$categoryId] = true;
            array_push($pendingTargets, ...($targetChildren[$categoryId] ?? []));
            if (isset($mappedSources[$categoryId])) {
                $pendingSources[] = $mappedSources[$categoryId];
            }
            if ($categoryId === $rootCategoryId) {
                array_push($pendingSources, ...($sourceChildren[''] ?? []));
            }
        }

        return ['sources' => $excluded, 'targets' => $protected];
    }
}
