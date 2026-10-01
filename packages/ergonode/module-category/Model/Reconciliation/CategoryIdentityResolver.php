<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Reconciliation;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function sprintf;
use function trim;

class CategoryIdentityResolver
{
    private readonly CategoryExclusionResolver $exclusionResolver;

    public function __construct(
        private readonly CategoryNameNormalizer $nameNormalizer,
        private readonly CategoryDeletionCandidateResolver $deletionCandidateResolver,
        ?CategoryExclusionResolver $exclusionResolver = null
    ) {
        $this->exclusionResolver = $exclusionResolver ?? new CategoryExclusionResolver();
    }

    /**
     * @param array<int, array<string, mixed>> $sourceCategories Parent-first order is not required.
     * @param array<int, array<string, mixed>> $magentoCategories Categories keyed by ID or containing `id`.
     * @param array<string, int> $databaseMappings
     * @param array<int, array{ergonode_code: string, magento_category_id: int}> $draftMappings
     * @return array{
     *     assignments: array<string, array{
     *         magento_category_id: int|null,
     *         source: string,
     *         expected_parent_id: int|null
     *     }>,
     *     conflicts: string[],
     *     consumed_magento_ids: int[],
     *     protected_magento_ids: int[],
     *     delete_candidates: int[],
     *     deletion_allowed: bool
     * }
     */
    public function resolve(
        int $rootCategoryId,
        array $sourceCategories,
        array $magentoCategories,
        array $databaseMappings = [],
        array $draftMappings = []
    ): array {
        $sources = $this->indexSources($sourceCategories);
        $magento = $this->indexMagento($magentoCategories);
        $children = $this->sourceChildren($sources);
        $this->inheritSourceExclusions($sources, $children);
        $magentoChildren = $this->magentoChildren($magento);
        $conflicts = [];
        $validDatabase = $this->validDatabaseMappings($sources, $magento, $databaseMappings, $conflicts);
        $draft = $this->validDraftMappings($sources, $magento, $validDatabase, $draftMappings, $conflicts);
        $mappings = $validDatabase + $draft;
        $exclusions = $this->exclusionResolver->resolve($rootCategoryId, $sources, $magento, $mappings);
        foreach ($exclusions['sources'] as $code => $_excluded) {
            $sources[$code]['active'] = false;
        }
        $protected = $exclusions['targets'];
        $reserved = array_fill_keys([...array_values($validDatabase), ...array_values($draft)], true);
        $assignments = [];
        $consumed = [];

        $this->resolveChildren(
            null,
            $rootCategoryId,
            $children,
            $sources,
            $magento,
            $magentoChildren,
            $validDatabase,
            $draft,
            $protected,
            $reserved,
            $assignments,
            $consumed,
            $conflicts
        );
        $this->markUnresolved($sources, $mappings, $assignments, $consumed, $conflicts);

        $deletion = $this->deletionCandidateResolver->resolve(
            $rootCategoryId,
            $sources,
            $magento,
            $databaseMappings,
            $consumed,
            $protected
        );

        return [
            'assignments' => $assignments,
            'conflicts' => array_values(array_unique($conflicts)),
            'consumed_magento_ids' => array_map('intval', array_keys($consumed)),
            'protected_magento_ids' => array_map('intval', array_keys($protected)),
            'delete_candidates' => $deletion['candidates'],
            'deletion_allowed' => $conflicts === [] && $deletion['conflicts'] === [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @param array<int, array<string, mixed>> $magento
     * @param array<string, int> $databaseMappings
     * @param string[] $conflicts
     * @return array<string, int>
     */
    private function validDatabaseMappings(
        array $sources,
        array $magento,
        array $databaseMappings,
        array &$conflicts
    ): array {
        $valid = [];
        $owners = [];
        foreach ($databaseMappings as $code => $categoryId) {
            $code = trim((string)$code);
            $categoryId = (int)$categoryId;
            if (!isset($sources[$code])) {
                continue;
            }
            if ($categoryId <= 0 || !isset($magento[$categoryId])) {
                $conflicts[] = sprintf('Stored mapping for "%s" points outside the configured Magento root.', $code);
                continue;
            }
            if (isset($owners[$categoryId]) && $owners[$categoryId] !== $code) {
                $conflicts[] = sprintf('Magento category #%d is used by more than one stored mapping.', $categoryId);
                unset($valid[$owners[$categoryId]]);
                continue;
            }
            $owners[$categoryId] = $code;
            $valid[$code] = $categoryId;
        }

        return $valid;
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @param array<int, array<string, mixed>> $magento
     * @param array<string, int> $databaseMappings
     * @param array<int, array{ergonode_code: string, magento_category_id: int}> $draftMappings
     * @param string[] $conflicts
     * @return array<string, int>
     */
    private function validDraftMappings(
        array $sources,
        array $magento,
        array $databaseMappings,
        array $draftMappings,
        array &$conflicts
    ): array {
        $databaseIds = array_fill_keys(array_values($databaseMappings), true);
        $draft = [];
        $draftOwners = [];
        foreach ($draftMappings as $mapping) {
            $code = trim((string)($mapping['ergonode_code'] ?? ''));
            $categoryId = (int)($mapping['magento_category_id'] ?? 0);
            if (!isset($sources[$code]) || !isset($magento[$categoryId])) {
                $conflicts[] = sprintf('Draft mapping "%s" points to data absent from the fresh trees.', $code);
                continue;
            }
            if (isset($databaseMappings[$code])) {
                $conflicts[] = sprintf('Draft mapping for "%s" was ignored because the database mapping wins.', $code);
                continue;
            }
            if (isset($databaseIds[$categoryId])) {
                $conflicts[] = sprintf('Draft target #%d is occupied by a database mapping.', $categoryId);
                continue;
            }
            if (empty($sources[$code]['active']) || empty($magento[$categoryId]['active'])) {
                $conflicts[] = sprintf('Draft mapping for "%s" uses an excluded category.', $code);
                continue;
            }
            if (isset($draftOwners[$categoryId]) && $draftOwners[$categoryId] !== $code) {
                $conflicts[] = sprintf('Draft target #%d is used more than once.', $categoryId);
                unset($draft[$draftOwners[$categoryId]]);
                continue;
            }
            $draftOwners[$categoryId] = $code;
            $draft[$code] = $categoryId;
        }

        return $draft;
    }

    /**
     * @param array<string, array<int, string>> $children
     * @param array<string, array<string, mixed>> $sources
     * @param array<int, array<string, mixed>> $magento
     * @param array<int, int[]> $magentoChildren
     * @param array<string, int> $database
     * @param array<string, int> $draft
     * @param array<int, true> $protected
     * @param array<int, true> $reserved Targets owned by identities anywhere in the source tree.
     * @param array<string, array{
     *     magento_category_id: int|null,
     *     source: string,
     *     expected_parent_id: int|null
     * }> $assignments
     * @param array<int, true> $consumed
     * @param string[] $conflicts
     */
    private function resolveChildren(
        ?string $parentCode,
        int $magentoParentId,
        array $children,
        array $sources,
        array $magento,
        array $magentoChildren,
        array $database,
        array $draft,
        array $protected,
        array $reserved,
        array &$assignments,
        array &$consumed,
        array &$conflicts
    ): void {
        $codes = $children[$parentCode ?? ''] ?? [];
        $unmatched = [];
        foreach ($codes as $code) {
            $categoryId = $database[$code] ?? $draft[$code] ?? null;
            if (empty($sources[$code]['active'])
                || isset($protected[$magentoParentId])
                || ($categoryId !== null && isset($protected[$categoryId]))
            ) {
                $assignments[$code] = [
                    'magento_category_id' => $categoryId,
                    'source' => 'excluded',
                    'expected_parent_id' => $magentoParentId,
                ];
                if ($categoryId !== null) {
                    $consumed[$categoryId] = true;
                }
                continue;
            }
            if (isset($database[$code])) {
                $this->assign($assignments, $consumed, $code, $database[$code], 'database', $magentoParentId);
                continue;
            }
            if (isset($draft[$code]) && !isset($consumed[$draft[$code]])) {
                $this->assign($assignments, $consumed, $code, $draft[$code], 'draft', $magentoParentId);
                continue;
            }
            $unmatched[] = $code;
        }

        $sourceNames = [];
        foreach ($unmatched as $code) {
            $name = $this->nameNormalizer->normalize((string)($sources[$code]['label'] ?? ''));
            if ($name !== '') {
                $sourceNames[$name][] = $code;
            }
        }
        $targetNames = [];
        foreach ($magentoChildren[$magentoParentId] ?? [] as $categoryId) {
            if (isset($consumed[$categoryId])
                || isset($reserved[$categoryId])
                || isset($protected[$categoryId])
                || empty($magento[$categoryId]['active'])
            ) {
                continue;
            }
            $name = $this->nameNormalizer->normalize((string)($magento[$categoryId]['label'] ?? ''));
            if ($name !== '') {
                $targetNames[$name][] = $categoryId;
            }
        }
        foreach ($unmatched as $code) {
            $name = $this->nameNormalizer->normalize((string)($sources[$code]['label'] ?? ''));
            $sourceCandidates = $sourceNames[$name] ?? [];
            $targetCandidates = $targetNames[$name] ?? [];
            if ($name !== '' && count($sourceCandidates) === 1 && count($targetCandidates) === 1) {
                $this->assign($assignments, $consumed, $code, $targetCandidates[0], 'name', $magentoParentId);
                continue;
            }
            $assignments[$code] = [
                'magento_category_id' => null,
                'source' => 'unmatched',
                'expected_parent_id' => $magentoParentId,
            ];
            if ($name !== '' && (count($sourceCandidates) > 1 || count($targetCandidates) > 1)) {
                $conflicts[] = sprintf(
                    'Ambiguous normalized name "%s" below Magento category #%d.',
                    $name,
                    $magentoParentId
                );
            }
        }

        foreach ($codes as $code) {
            $categoryId = $assignments[$code]['magento_category_id'] ?? null;
            if ($categoryId === null) {
                continue;
            }
            $this->resolveChildren(
                $code,
                $categoryId,
                $children,
                $sources,
                $magento,
                $magentoChildren,
                $database,
                $draft,
                $protected,
                $reserved,
                $assignments,
                $consumed,
                $conflicts
            );
        }
    }

    /**
     * @param array<string, array{
     *     magento_category_id: int|null,
     *     source: string,
     *     expected_parent_id: int|null
     * }> $assignments
     * @param array<int, true> $consumed
     */
    private function assign(
        array &$assignments,
        array &$consumed,
        string $code,
        int $categoryId,
        string $source,
        int $expectedParentId
    ): void {
        if (isset($consumed[$categoryId])) {
            $assignments[$code] = [
                'magento_category_id' => null,
                'source' => 'unmatched',
                'expected_parent_id' => $expectedParentId,
            ];
            return;
        }
        $assignments[$code] = [
            'magento_category_id' => $categoryId,
            'source' => $source,
            'expected_parent_id' => $expectedParentId,
        ];
        $consumed[$categoryId] = true;
    }

    /**
     * @param array<int, array<string, mixed>> $sourceCategories
     * @return array<string, array<string, mixed>>
     */
    private function indexSources(array $sourceCategories): array
    {
        $sources = [];
        foreach ($sourceCategories as $index => $source) {
            $code = trim((string)($source['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $source['code'] = $code;
            $parentCode = trim((string)($source['parent_code'] ?? ''));
            $source['parent_code'] = $parentCode !== '' ? $parentCode : null;
            $source['sort_order'] = (int)($source['sort_order'] ?? $index);
            $source['active'] = !array_key_exists('active', $source) || !empty($source['active']);
            $sources[$code] = $source;
        }

        return $sources;
    }

    /**
     * @param array<int, array<string, mixed>> $magentoCategories
     * @return array<int, array<string, mixed>>
     */
    private function indexMagento(array $magentoCategories): array
    {
        $magento = [];
        foreach ($magentoCategories as $key => $category) {
            $categoryId = (int)($category['id'] ?? $key);
            if ($categoryId <= 0) {
                continue;
            }
            $category['id'] = $categoryId;
            $category['active'] = !array_key_exists('active', $category) || !empty($category['active']);
            $magento[$categoryId] = $category;
        }

        return $magento;
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @return array<string, array<int, string>>
     */
    private function sourceChildren(array $sources): array
    {
        $children = [];
        foreach ($sources as $code => $source) {
            $children[(string)($source['parent_code'] ?? '')][] = (string)$code;
        }
        foreach ($children as &$group) {
            usort($group, static fn (string $first, string $second): int => [
                (int)$sources[$first]['sort_order'], $first,
            ] <=> [
                (int)$sources[$second]['sort_order'], $second,
            ]);
        }
        unset($group);

        return $children;
    }

    /**
     * @param array<int, array<string, mixed>> $magento
     * @return array<int, int[]>
     */
    private function magentoChildren(array $magento): array
    {
        $children = [];
        foreach ($magento as $categoryId => $category) {
            $children[(int)($category['parent_id'] ?? 0)][] = $categoryId;
        }

        return $children;
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @param array<string, array{
     *     magento_category_id: int|null,
     *     source: string,
     *     expected_parent_id: int|null
     * }> $assignments
     * @param array<string, int> $database
     * @param array<int, true> $consumed
     * @param string[] $conflicts
     */
    private function markUnresolved(
        array $sources,
        array $database,
        array &$assignments,
        array &$consumed,
        array &$conflicts
    ): void {
        foreach ($sources as $code => $source) {
            if (isset($assignments[$code])) {
                continue;
            }
            $assignments[$code] = [
                'magento_category_id' => empty($source['active']) ? ($database[$code] ?? null) : null,
                'source' => empty($source['active']) ? 'excluded' : 'unmatched',
                'expected_parent_id' => null,
            ];
            if (empty($source['active'])) {
                if (isset($database[$code])) {
                    $consumed[$database[$code]] = true;
                }
                continue;
            }
            $conflicts[] = sprintf('Parent of Ergonode category "%s" could not be resolved.', $code);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $sources
     * @param array<string, string[]> $children
     */
    private function inheritSourceExclusions(array &$sources, array $children): void
    {
        $pending = array_keys(array_filter($sources, static fn (array $source): bool => empty($source['active'])));
        $excluded = array_fill_keys($pending, true);
        while ($pending !== []) {
            $code = array_pop($pending);
            foreach ($children[$code] ?? [] as $child) {
                if (isset($excluded[$child])) {
                    continue;
                }
                $excluded[$child] = true;
                $sources[$child]['active'] = false;
                $pending[] = $child;
            }
        }
    }
}
