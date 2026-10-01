<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeSourceState;
use Ergonode\Category\Model\Import\CategoryTreeSourceChecker;
use Ergonode\Category\Model\Import\MissingCategoryTreeException;

class CategorySourceAvailability
{
    public function __construct(
        private readonly CategoryTreeQuery $treeQuery,
        private readonly CategoryTreeSourceChecker $checker,
        private readonly CategoryTreeSourceState $sourceState
    ) {
    }

    /** @return array<int, string> Missing sources, keyed by local tree ID. */
    public function checkActiveTrees(bool $data = false): array
    {
        $missing = [];
        foreach ($this->treeQuery->getList(true) as $tree) {
            $treeId = (int)$tree['category_tree_id'];
            try {
                $this->checker->check($treeId, (string)$tree['tree_code']);
                if ($data && $this->sourceState->get($treeId)['requires_refresh']) {
                    $missing[$treeId] = (string)__(
                        'Refresh source tree "%1" before synchronizing category data.',
                        $tree['tree_code']
                    );
                }
            } catch (MissingCategoryTreeException $exception) {
                $missing[$treeId] = $exception->getMessage();
            }
        }

        return $missing;
    }

    /** @return string[] */
    public function getRefreshCodes(): array
    {
        $codes = [];
        foreach ($this->treeQuery->getList(true) as $tree) {
            if ($this->sourceState->get((int)$tree['category_tree_id'])['requires_refresh']) {
                $codes[] = (string)$tree['tree_code'];
            }
        }

        return $codes;
    }

    public function assertCanUseSnapshot(int $treeId): void
    {
        $this->sourceState->assertCanUseSnapshot($treeId);
    }
}
