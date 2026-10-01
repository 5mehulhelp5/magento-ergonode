<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationActionInterface;
use Ergonode\CategoryConsumer\Api\CategoryStructureSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryDataSynchronizationProcessInterface;
use Ergonode\CategoryConsumer\Api\CategoryTreeSyncCursorResetterInterface;
use Ergonode\CategoryConsumer\Model\Import\CategoryDataSyncCursorResetter;
use Ergonode\CategoryConsumer\Model\Config\CategoryConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\CategoryConsumer\Model\Import\CategoryTreeStreamImporter;
use Ergonode\CategoryConsumer\Model\Import\CategoryEntityStreamImporter;

class CategorySynchronizationAction implements CategorySynchronizationActionInterface
{
    public function __construct(
        private readonly CategoryStructureSynchronizationProcessInterface $treeProcess,
        private readonly CategoryDataSynchronizationProcessInterface $dataProcess,
        private readonly CategoryTreeSyncCursorResetterInterface $treeResetter,
        private readonly CategoryDataSyncCursorResetter $dataResetter,
        private readonly CategoryConfigProvider $configProvider,
        private readonly CategorySynchronizationProgress $progress,
        private readonly CursorStorage $cursorStorage
    ) {
    }

    public function execute(string $scope, string $action): array
    {
        if (!in_array($scope, ['tree', 'data', 'all'], true)
            || !in_array($action, ['sync', 'reset-cursor', 'reset-cursor-and-sync'], true)
        ) {
            throw new LocalizedException(__('Choose an update action.'));
        }
        $results = [];
        $reset = $action === 'reset-cursor-and-sync';
        if ($scope !== 'data') {
            if ($action === 'reset-cursor') {
                $this->treeResetter->reset();
            } else {
                $results['tree'] = $this->progress->getStageResult('tree') ?? $this->treeProcess->execute($reset);
                $this->progress->completeStage('tree', $results['tree']);
            }
        }
        if ($scope === 'data' || ($scope === 'all' && $this->configProvider->isDataSynchronizationEnabled())) {
            if ($action === 'reset-cursor') {
                $this->dataResetter->reset();
            } else {
                $results['data'] = $this->progress->getStageResult('data') ?? $this->dataProcess->execute($reset);
                $this->progress->completeStage('data', $results['data']);
            }
        }

        $this->commitCursors($results);

        return [
            'events' => (int)($results['tree']['events'] ?? 0) + (int)($results['data']['events'] ?? 0),
            'conflicts' => (int)($results['tree']['conflicts'] ?? 0),
            'results' => $results,
        ];
    }

    /** @param array<string, array<string, mixed>> $results */
    private function commitCursors(array $results): void
    {
        if (!$this->progress->isManaged() || $results === [] || (int)($results['tree']['conflicts'] ?? 0) > 0) {
            return;
        }
        $this->progress->checkpoint('saving_cursors');
        $processes = [
            'tree' => CategoryTreeStreamImporter::PROCESS_CODE,
            'data' => CategoryEntityStreamImporter::PROCESS_CODE,
        ];
        foreach ($processes as $stage => $process) {
            $cursor = $results[$stage]['cursor'] ?? null;
            if ($cursor !== null && empty($results[$stage]['skipped'])) {
                $this->cursorStorage->save($process, $cursor);
            }
        }
    }
}
