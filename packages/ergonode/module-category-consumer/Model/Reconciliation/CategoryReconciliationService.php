<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Reconciliation;

use function array_unique;
use function array_values;
use function count;

use Ergonode\CategoryConsumer\Api\CategoryReconciliationServiceInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationResultInterface;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationResult;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Reconciliation\CategoryIdentityResolver;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;

use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

use function in_array;

use Magento\Framework\Exception\LocalizedException;

class CategoryReconciliationService implements CategoryReconciliationServiceInterface
{
    public function __construct(
        private readonly CategoryReconciliationInputProvider $inputProvider,
        private readonly CategoryIdentityResolver $identityResolver,
        private readonly CategoryReconciliationExecutor $executor,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly CategorySynchronizationLock $synchronizationLock,
        private readonly CategorySourceAvailability $sourceAvailability,
        private readonly CategoryCacheInvalidator $cacheInvalidator
    ) {
    }

    public function execute(CategoryReconciliationRequestInterface $request): CategoryReconciliationResultInterface
    {
        if (!in_array($request->getMode(), [
            CategoryReconciliationRequestInterface::MODE_PREVIEW,
            CategoryReconciliationRequestInterface::MODE_APPLY,
        ], true)) {
            throw new LocalizedException(__('Unsupported category reconciliation mode.'));
        }
        return $this->synchronizationLock->execute(
            fn (): CategoryReconciliationResultInterface => $this->cacheInvalidator->defer(
                fn (): CategoryReconciliationResultInterface => $this->reconcile($request)
            )
        );
    }

    private function reconcile(
        CategoryReconciliationRequestInterface $request
    ): CategoryReconciliationResultInterface {
        if ($request->getMode() === CategoryReconciliationRequestInterface::MODE_PREVIEW) {
            $this->sourceAvailability->assertCanUseSnapshot($request->getCategoryTreeId());
        }
        $input = $this->inputProvider->get($request);
        $tree = $input['tree'];
        $sources = $input['sources'];
        $databaseMappings = $input['database_mappings'];
        $resolution = $this->identityResolver->resolve(
            (int)$tree['root_category_id'],
            $sources,
            $input['magento'],
            $databaseMappings,
            $request->getDraftMappings()
        );
        $execution = ['created' => 0, 'moved' => 0, 'updated' => 0, 'errors' => []];

        if ($request->getMode() === CategoryReconciliationRequestInterface::MODE_APPLY) {
            [$resolution, $execution] = $this->apply(
                $request,
                $tree,
                $sources,
                $databaseMappings,
                $resolution
            );
        }

        $magento = $request->getMode() === CategoryReconciliationRequestInterface::MODE_APPLY
            ? $this->magentoCategoryProvider->getCategories((int)$tree['root_category_id'])
            : $input['magento'];

        return $this->result($sources, $magento, $resolution, $execution);
    }

    /**
     * @param array<string, mixed> $tree
     * @param array<int, array<string, mixed>> $sources
     * @param array<string, int> $databaseMappings
     * @param array<string, mixed> $resolution
     * @return array{array<string, mixed>, array{created: int, moved: int, updated: int, errors: string[]}}
     */
    private function apply(
        CategoryReconciliationRequestInterface $request,
        array $tree,
        array $sources,
        array $databaseMappings,
        array $resolution
    ): array {
        $summary = ['created' => 0, 'moved' => 0, 'updated' => 0, 'errors' => []];
        $completedMappings = [];
        $maxPasses = count($sources) + 1;
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $result = $this->executor->executePass(
                $request->getCategoryTreeId(),
                (int)$tree['root_category_id'],
                $sources,
                $resolution,
                $completedMappings
            );
            // Writes preserve the working position index during a pass; refresh labels and paths between passes.
            $this->cacheInvalidator->refreshCategoryIndex();
            foreach (['created', 'moved', 'updated'] as $field) {
                $summary[$field] += (int)$result[$field];
            }
            $summary['errors'] = [...$summary['errors'], ...$result['errors']];
            $completedMappings = $result['mappings'];
            $databaseMappings = array_replace($databaseMappings, $result['mappings']);
            if ($result['created'] === 0 || $result['errors'] !== []) {
                break;
            }
            $resolution = $this->identityResolver->resolve(
                (int)$tree['root_category_id'],
                $sources,
                $this->magentoCategoryProvider->getCategories((int)$tree['root_category_id']),
                $databaseMappings,
                $request->getDraftMappings()
            );
            if (!$this->hasPendingAssignments($resolution, $completedMappings)) {
                break;
            }
        }

        return [$resolution, $summary];
    }

    /**
     * @param array<string, mixed> $resolution
     * @param array<string, int> $completedMappings
     */
    private function hasPendingAssignments(array $resolution, array $completedMappings): bool
    {
        foreach ($resolution['assignments'] as $code => $assignment) {
            if (!isset($completedMappings[$code])
                && $assignment['source'] !== 'excluded'
                && $assignment['expected_parent_id'] !== null
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @param array<int, array<string, mixed>> $magento
     * @param array<string, mixed> $resolution
     * @param array{created: int, moved: int, updated: int, errors: string[]} $execution
     */
    private function result(
        array $sources,
        array $magento,
        array $resolution,
        array $execution
    ): CategoryReconciliationResultInterface {
        $categories = [];
        $stats = [
            'database' => 0,
            'draft' => 0,
            'name' => 0,
            'unmatched' => 0,
            'moved' => $execution['moved'],
            'created' => $execution['created'],
            'updated' => $execution['updated'],
            'deleted' => 0,
            'delete_candidates' => count($resolution['delete_candidates']),
        ];
        foreach ($sources as $source) {
            $assignment = $resolution['assignments'][(string)$source['code']] ?? [
                'magento_category_id' => null,
                'source' => 'unmatched',
                'expected_parent_id' => null,
            ];
            if (isset($stats[$assignment['source']])) {
                $stats[$assignment['source']]++;
            }
            $source['magento_category_id'] = $assignment['magento_category_id'];
            $source['mapping_source'] = $assignment['source'];
            $source['expected_parent_id'] = $assignment['expected_parent_id'];
            $categories[] = $source;
        }
        $consumed = array_fill_keys($resolution['consumed_magento_ids'], true);
        $protected = array_fill_keys($resolution['protected_magento_ids'], true);
        $magentoResult = [];
        foreach ($magento as $categoryId => $category) {
            $category['consumed'] = isset($consumed[$categoryId]);
            $category['protected'] = isset($protected[$categoryId]);
            $magentoResult[] = $category;
        }
        $conflicts = array_values(array_unique([...$execution['errors'], ...$resolution['conflicts']]));

        return (new CategoryReconciliationResult())
            ->setCategories($categories)
            ->setMagentoCategories($magentoResult)
            ->setConflicts($conflicts)
            ->setStats($stats)
            ->setDeleteCandidates($resolution['delete_candidates']);
    }
}
