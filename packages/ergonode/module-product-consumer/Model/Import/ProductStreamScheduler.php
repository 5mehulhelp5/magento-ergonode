<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Import;

use Ergonode\Core\Model\Import\CursorStorage;
use Ergonode\ProductConsumer\Model\Config\ProductImportConfig;
use Ergonode\ProductConsumer\Model\GraphQl\ProductStreamPageReader;
use Ergonode\ProductConsumer\Model\Port\ProductImportWorkRepositoryInterface;
use Ergonode\ProductConsumer\Model\Queue\ProductImportQueuePublisher;

class ProductStreamScheduler
{
    public const string CHANGED_PROCESS_CODE = 'product_stream';
    public const string DELETED_PROCESS_CODE = 'product_deleted_stream';

    public function __construct(
        private readonly ProductStreamPageReader $pageReader,
        private readonly ProductImportWorkRepositoryInterface $workRepository,
        private readonly CursorStorage $cursorStorage,
        private readonly ProductImportQueuePublisher $queuePublisher,
        private readonly ProductImportConfig $config
    ) {
    }

    /** @return array{pages: int, changed: int, deleted: int, throttled: bool} */
    public function schedule(int $maximumPages = 1): array
    {
        $summary = ['pages' => 0, 'changed' => 0, 'deleted' => 0, 'throttled' => false];
        if (!$this->config->isEnabled()) {
            return $summary;
        }
        $pageSize = $this->config->getStreamPageSize();
        $maximumPages = max(1, $maximumPages);
        for ($page = 0; $page < $maximumPages; $page++) {
            if ($this->workRepository->countActive() >= $this->config->getMaximumPendingItems()) {
                $summary['throttled'] = true;
                break;
            }
            $changed = $this->scheduleChangedPage($pageSize);
            $deleted = $this->scheduleDeletedPage($pageSize);
            $summary['pages']++;
            $summary['changed'] += $changed['scheduled'];
            $summary['deleted'] += $deleted['scheduled'];
            if (!$changed['has_more'] && !$deleted['has_more']) {
                break;
            }
        }
        if ($summary['changed'] + $summary['deleted'] > 0) {
            $this->queuePublisher->dispatch();
        }

        return $summary;
    }

    /** @return array{scheduled: int, has_more: bool} */
    private function scheduleChangedPage(int $pageSize): array
    {
        $state = $this->cursorStorage->get(self::CHANGED_PROCESS_CODE);
        $page = $this->pageReader->readChanged($state['cursor'] ?? null, $pageSize);
        $scheduled = $this->workRepository->scheduleSynchronizations($page['items']);
        $this->saveCursor(self::CHANGED_PROCESS_CODE, $page['cursor']);

        return ['scheduled' => $scheduled, 'has_more' => $page['has_more']];
    }

    /** @return array{scheduled: int, has_more: bool} */
    private function scheduleDeletedPage(int $pageSize): array
    {
        $state = $this->cursorStorage->get(self::DELETED_PROCESS_CODE);
        $page = $this->pageReader->readDeleted($state['cursor'] ?? null, $pageSize);
        $scheduled = $this->workRepository->scheduleDeletions($page['items']);
        $this->saveCursor(self::DELETED_PROCESS_CODE, $page['cursor']);

        return ['scheduled' => $scheduled, 'has_more' => $page['has_more']];
    }

    private function saveCursor(string $processCode, ?string $cursor): void
    {
        if ($cursor !== null && $cursor !== '') {
            $this->cursorStorage->save($processCode, $cursor);
        }
    }
}
