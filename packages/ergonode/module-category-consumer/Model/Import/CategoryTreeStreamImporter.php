<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Import;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;

use Ergonode\Category\Model\GraphQl\CategoryQueries;
use Ergonode\Category\Model\Import\CategoryStreamPageReader;
use Ergonode\Category\Model\Import\MissingCategoryTreeException;

use Ergonode\CategoryConsumer\Api\CategoryReconciliationServiceInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationRequest;
use Ergonode\CategoryConsumer\Model\Data\CategoryReconciliationResult;
use Ergonode\CategoryConsumer\Model\Sync\CategorySourceAvailability;
use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;

class CategoryTreeStreamImporter
{
    public const string PROCESS_CODE = 'category_tree_stream';

    public function __construct(
        private readonly CategoryStreamPageReader $pageReader,
        private readonly CursorStorage $cursorStorage,
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryReconciliationServiceInterface $reconciliationService,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly ChangeReport $changeReport,
        private readonly CategorySynchronizationProgress $progress,
        private readonly CategorySourceAvailability $sourceAvailability
    ) {
    }

    /** @return array{events: int, trees: int, conflicts: int, cursor: string|null, tree_results: list<array<string, mixed>>} */
    public function execute(bool $resetCursor = false): array
    {
        if ($resetCursor) {
            $this->cursorStorage->reset(self::PROCESS_CODE);
        }
        $missing = $this->sourceAvailability->checkActiveTrees();
        $this->progress->checkpoint('fetching_tree_changes');
        $this->languageMappingProvider->getLanguageStoreMap();

        $state = $resetCursor ? null : $this->cursorStorage->get(self::PROCESS_CODE);
        $cursor = $state['cursor'] ?? null;
        $stream = $this->pageReader->readAll(
            CategoryQueries::CATEGORY_TREE_STREAM,
            'categoryTreeStream',
            $cursor
        );
        $codes = $stream['codes'];
        $cursor = $stream['cursor'];
        $conflicts = count($missing);
        $trees = $resetCursor
            ? $this->categoryTreeQuery->getList(true)
            : $this->categoryTreeQuery->getSynchronizableByTreeCodes(
                array_values(array_unique([...$codes, ...$this->sourceAvailability->getRefreshCodes()]))
            );
        $treeResults = [];
        foreach ($missing as $treeId => $message) {
            $treeResults[] = [
                'category_tree_id' => $treeId,
                'tree_code' => (string)$this->categoryTreeQuery->getById($treeId)['tree_code'],
                'stats' => [], 'conflict_count' => 1, 'conflicts' => [$message],
            ];
            $this->changeReport->add('category_tree', (string)$treeId, ChangeReport::ACTION_ERROR, $message);
        }
        usort($trees, static fn (array $left, array $right): int => strcmp(
            (string)$left['tree_code'],
            (string)$right['tree_code']
        ));
        foreach ($trees as $index => $tree) {
            if (isset($missing[(int)$tree['category_tree_id']])) {
                continue;
            }
            $this->progress->startTree((string)$tree['tree_code'], $index + 1, count($trees));
            try {
                $result = $this->reconciliationService->execute(
                    (new CategoryReconciliationRequest())
                        ->setCategoryTreeId((int)$tree['category_tree_id'])
                        ->setMode(CategoryReconciliationRequestInterface::MODE_APPLY)
                );
            } catch (MissingCategoryTreeException $exception) {
                $result = (new CategoryReconciliationResult())
                    ->setConflicts([$exception->getMessage()]);
            }
            $conflicts += count($result->getConflicts());
            $treeResults[] = [
                'tree_code' => (string)$tree['tree_code'],
                'category_tree_id' => (int)$tree['category_tree_id'],
                'stats' => $result->getStats(),
                'conflict_count' => count($result->getConflicts()),
                'conflicts' => array_slice($result->getConflicts(), 0, 10),
            ];
            foreach ($result->getConflicts() as $conflict) {
                $this->changeReport->add(
                    'category_tree',
                    (string)$tree['tree_code'],
                    ChangeReport::ACTION_ERROR,
                    $conflict,
                    ['category_tree_id' => (int)$tree['category_tree_id']]
                );
            }
        }
        $this->progress->checkpoint('saving_tree_cursor');
        if ($conflicts > 0) {
            $cursor = $state['cursor'] ?? null;
        } elseif ($cursor !== null && !$this->progress->isManaged()) {
            $this->cursorStorage->save(self::PROCESS_CODE, $cursor);
        }

        return [
            'events' => count($codes), 'trees' => count($trees), 'conflicts' => $conflicts,
            'cursor' => $cursor, 'tree_results' => $treeResults,
        ];
    }
}
