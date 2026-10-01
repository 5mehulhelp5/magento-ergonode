<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Reconciliation;

use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCreationService;
use Ergonode\CategoryConsumer\Api\MappedCategoryAttributeSynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\CategoryConsumer\Api\CategoryPositionWriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Throwable;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationPaused;

use function count;
use function trim;

class CategoryReconciliationExecutor
{
    private int $processed = 0;
    private int $total = 0;
    private bool $synchronizeAttributes = true;

    public function __construct(
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly CategoryMappingWriter $categoryMappingWriter,
        private readonly CategoryCreationService $categoryCreationService,
        private readonly CategoryPositionWriterInterface $positionWriter,
        private readonly MappedCategoryAttributeSynchronizerInterface $attributeSynchronizer,
        private readonly ChangeReport $changeReport,
        private readonly CategorySynchronizationProgress $progress
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @param array<string, mixed> $resolution
     * @param array<string, int> $completedMappings Successful categories from earlier passes of this apply only.
     * @return array{created: int, moved: int, updated: int, mappings: array<string, int>, errors: string[]}
     */
    public function executePass(
        int $categoryTreeId,
        int $rootCategoryId,
        array $sources,
        array $resolution,
        array $completedMappings = []
    ): array {
        $this->processed = count($completedMappings);
        $this->total = count($sources);
        $this->synchronizeAttributes = !$this->attributeSynchronizer instanceof CategoryDataWorkProviderInterface
            || $this->attributeSynchronizer->hasWork();
        $this->progress->checkpoint('applying_tree', $this->processed, $this->total);
        $indexed = [];
        $children = [];
        foreach ($sources as $source) {
            $code = (string)$source['code'];
            $indexed[$code] = $source;
            $children[(string)($source['parent_code'] ?? '')][] = $code;
        }
        foreach ($children as &$group) {
            usort($group, static fn (string $first, string $second): int => [
                (int)$indexed[$first]['sort_order'], $first,
            ] <=> [
                (int)$indexed[$second]['sort_order'], $second,
            ]);
        }
        unset($group);
        $result = ['created' => 0, 'moved' => 0, 'updated' => 0, 'mappings' => $completedMappings, 'errors' => []];
        $this->executeChildren(
            null,
            $rootCategoryId,
            $categoryTreeId,
            $rootCategoryId,
            $children,
            $indexed,
            (array)$resolution['assignments'],
            $result
        );

        $this->progress->checkpoint('applying_tree', $this->processed, $this->total);

        return $result;
    }

    /**
     * @param array<string, string[]> $children
     * @param array<string, array<string, mixed>> $sources
     * @param array<string, array<string, mixed>> $assignments
     * @param array{created: int, moved: int, updated: int, mappings: array<string, int>, errors: string[]} $result
     */
    private function executeChildren(
        ?string $parentCode,
        int $parentId,
        int $categoryTreeId,
        int $rootCategoryId,
        array $children,
        array $sources,
        array $assignments,
        array &$result
    ): void {
        $previousCategoryId = 0;
        foreach ($children[$parentCode ?? ''] ?? [] as $position => $code) {
            $assignment = $assignments[$code] ?? null;
            if (!is_array($assignment)
                || $assignment['source'] === 'excluded'
                || $assignment['expected_parent_id'] === null
            ) {
                continue;
            }

            if (isset($result['mappings'][$code])) {
                // Parents still lead to newly resolved children; their own writes and progress are complete.
                $categoryId = $result['mappings'][$code];
                $previousCategoryId = $categoryId;
                $this->executeChildren(
                    $code,
                    $categoryId,
                    $categoryTreeId,
                    $rootCategoryId,
                    $children,
                    $sources,
                    $assignments,
                    $result
                );
                continue;
            }

            $this->progress->checkpoint('applying_tree', $this->processed, $this->total, $code);
            try {
                $source = $sources[$code];
                $label = trim((string)($source['label'] ?? ''));
                if ($label === '') {
                    throw new LocalizedException(__('Ergonode category "%1" has no usable label.', $code));
                }
                $categoryId = (int)($assignment['magento_category_id'] ?? 0);
                $created = false;
                if ($categoryId <= 0) {
                    $this->progress->checkpoint('creating_category', $this->processed, $this->total, $code);
                    $category = $this->categoryCreationService->create($code, $label, $parentId);
                    $this->progress->completedOperation('created');
                    $categoryId = (int)$category['id'];
                    $created = true;
                    $this->magentoCategoryProvider->addOrUpdate($rootCategoryId, $category);
                    $this->categoryMappingWriter->updateMagentoLink(
                        $categoryTreeId,
                        $code,
                        $categoryId,
                        'synced',
                        'Created Magento category.'
                    );
                    $result['created']++;
                    $this->changeReport->add(
                        'category',
                        $code,
                        ChangeReport::ACTION_INSERTED,
                        'Created Magento category.',
                        ['category_id' => $categoryId, 'parent_id' => $parentId]
                    );
                }
                if (!$created && (string)$assignment['source'] !== 'database' && $this->synchronizeAttributes) {
                    $this->attributeSynchronizer->synchronize($code, $categoryId);
                }

                if ($this->moveIfNeeded($rootCategoryId, $categoryId, $parentId, $previousCategoryId)) {
                    $result['moved']++;
                    $this->changeReport->add(
                        'category',
                        $code,
                        ChangeReport::ACTION_UPDATED,
                        'Moved Magento category.',
                        ['category_id' => $categoryId, 'parent_id' => $parentId, 'position' => $position + 1]
                    );
                }
                $this->categoryMappingWriter->updateMagentoLink(
                    $categoryTreeId,
                    $code,
                    $categoryId,
                    'synced',
                    'Reconciled Magento category.'
                );
                $result['mappings'][$code] = $categoryId;
                $previousCategoryId = $categoryId;
                $this->processed++;
                $this->executeChildren(
                    $code,
                    $categoryId,
                    $categoryTreeId,
                    $rootCategoryId,
                    $children,
                    $sources,
                    $assignments,
                    $result
                );
            } catch (CategorySynchronizationPaused $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $this->categoryMappingWriter->updateSyncStatus(
                    $categoryTreeId,
                    $code,
                    'error',
                    $exception->getMessage()
                );
                $result['errors'][] = (string)__(
                    'Unable to reconcile category "%1": %2',
                    $code,
                    $exception->getMessage()
                );
                $this->changeReport->add(
                    'category',
                    $code,
                    ChangeReport::ACTION_ERROR,
                    $exception->getMessage()
                );
            }
        }
    }

    private function moveIfNeeded(
        int $rootCategoryId,
        int $categoryId,
        int $parentId,
        int $previousCategoryId
    ): bool {
        $category = $this->magentoCategoryProvider->getCategory($categoryId, $rootCategoryId);
        if ($category !== null
            && (int)$category['parent_id'] === $parentId
            && $this->magentoCategoryProvider->getPreviousSiblingId($categoryId, $rootCategoryId)
                === $previousCategoryId
        ) {
            return false;
        }
        $previous = $previousCategoryId > 0
            ? $this->magentoCategoryProvider->getCategory($previousCategoryId, $rootCategoryId) : null;
        $position = (int)($previous['position'] ?? 0) + 1;
        if ($previous !== null && $category !== null
            && (int)$category['parent_id'] === $parentId
            && (int)$category['position'] < (int)$previous['position']
        ) {
            $position--;
        }
        $this->progress->checkpoint('moving_category', $this->processed, $this->total, (string)$categoryId);
        $this->positionWriter->move($categoryId, $parentId, $previousCategoryId);
        $this->progress->completedOperation('moved');
        $this->magentoCategoryProvider->markMoved($rootCategoryId, $categoryId, $parentId, $position);

        return true;
    }
}
