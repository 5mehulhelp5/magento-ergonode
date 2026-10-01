<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Provider;

use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryTreeStateProviderInterface;
use Ergonode\Category\Model\Reconciliation\CategoryExclusionResolver;

class CategoryDataMappingProvider
{
    /** @var array<int, array<string, mixed>> */
    private array $states = [];
    private readonly CategoryExclusionResolver $exclusionResolver;

    public function clear(): void
    {
        $this->states = [];
    }

    public function __construct(
        private readonly CategoryMappingQuery $mappingQuery,
        private readonly CategoryTreeStateProviderInterface $stateProvider,
        ?CategoryExclusionResolver $exclusionResolver = null
    ) {
        $this->exclusionResolver = $exclusionResolver ?? new CategoryExclusionResolver();
    }

    /**
     * @param string[] $codes
     * @return array<string, list<array{category_tree_id: int, magento_category_id: int}>>
     */
    public function getValidMappingsByCodes(array $codes): array
    {
        $result = [];
        foreach ($this->mappingQuery->getValidMappingsByCodes($codes) as $code => $mappings) {
            foreach ($mappings as $mapping) {
                $treeId = $mapping['category_tree_id'];
                if (!isset($this->states[$treeId])) {
                    $this->states[$treeId] = $this->prepareState($this->stateProvider->getState($treeId));
                }
                $state = $this->states[$treeId];
                $categoryId = $mapping['magento_category_id'];
                if ($state['active'] && $this->isMappingIncluded($state, (string)$code, $categoryId)) {
                    $result[$code][] = $mapping;
                }
            }
        }

        return $result;
    }

    /**
     * Qualify manual backfill from fresh accepted state, independently of stream eligibility and cache.
     *
     * @param list<array{category_id: int, entity: array<string, mixed>}> $operations
     * @return list<array{category_id: int, entity: array<string, mixed>}>
     */
    public function filterForTree(int $categoryTreeId, array $operations): array
    {
        if ($operations === []) {
            return [];
        }
        $state = $this->prepareState($this->stateProvider->getState($categoryTreeId));
        return array_values(array_filter(
            $operations,
            fn (array $operation): bool => $this->isMappingIncluded(
                $state,
                (string)$operation['entity']['code'],
                $operation['category_id']
            )
        ));
    }

    /** @param array<string, mixed> $state */
    private function isMappingIncluded(array $state, string $code, int $categoryId): bool
    {
        return (int)($state['source'][$code]['magento_category_id'] ?? 0) === $categoryId
            && !isset($state['exclusions']['sources'][$code])
            && !isset($state['exclusions']['targets'][$categoryId])
            && $this->isIncluded($state['source'], $code)
            && $this->isIncluded($state['target'], (string)$categoryId);
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function prepareState(array $state): array
    {
        $sources = array_column($state['source'], null, 'identifier');
        $targets = array_column($state['target'], null, 'identifier');
        $sourceNodes = [];
        $targetNodes = [];
        $mappings = [];
        foreach ($sources as $code => $source) {
            $sourceNodes[$code] = [
                'parent_code' => $source['parent_identifier'] ?? null, 'active' => $source['active'],
            ];
            $categoryId = (int)($source['magento_category_id'] ?? 0);
            if ($categoryId > 0 && isset($targets[$categoryId])) {
                $mappings[$code] = $categoryId;
            }
        }
        foreach ($targets as $id => $target) {
            $targetNodes[(int)$id] = [
                'parent_id' => (int)($target['parent_identifier'] ?? 0), 'active' => $target['active'],
            ];
        }

        return [
            'active' => $state['tree']['is_active'],
            'source' => $sources,
            'target' => $targets,
            'exclusions' => $this->exclusionResolver->resolve(
                (int)$state['tree']['root_category_id'],
                $sourceNodes,
                $targetNodes,
                $mappings
            ),
        ];
    }

    /** @param array<string|int, array<string, mixed>> $nodes */
    private function isIncluded(array $nodes, string $identifier): bool
    {
        $visited = [];
        while ($identifier !== '') {
            if (isset($visited[$identifier]) || !isset($nodes[$identifier]) || !$nodes[$identifier]['active']) {
                return false;
            }
            $visited[$identifier] = true;
            $identifier = (string)($nodes[$identifier]['parent_identifier'] ?? '');
        }

        return true;
    }
}
