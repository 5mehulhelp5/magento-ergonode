<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Magento\Framework\Exception\LocalizedException;

class TemplateListImporter
{
    public function __construct(
        private readonly TemplateListPageReader $pageReader,
        private readonly ImportedTemplateProcessor $templateProcessor,
        private readonly TemplateSnapshotReconciler $snapshotReconciler,
        private readonly TemplateSynchronizationProcessor $synchronizationProcessor
    ) {
    }

    /** @return array{events: int, imported: int, changed: int, unchanged: int, cursor: null} */
    public function execute(bool $synchronize = true): array
    {
        $cursor = null;
        $codes = [];
        $seenCursors = [];
        do {
            $page = $this->pageReader->read($cursor);
            $codes = [...$codes, ...$page['codes']];
            $cursor = $page['cursor'] ?? $cursor;
            if ($page['has_more']) {
                if ($cursor === null || isset($seenCursors[$cursor])) {
                    throw new LocalizedException(__('Ergonode returned invalid templateList pagination.'));
                }
                $seenCursors[$cursor] = true;
            }
        } while ($page['has_more']);
        $codes = array_values(array_unique($codes));
        $stats = $this->templateProcessor->process($codes);
        $deleted = $this->snapshotReconciler->reconcile($codes);
        if ($synchronize) {
            $this->synchronizationProcessor->execute($codes, $this->snapshotReconciler->getDeletedCodes());
        }

        return [
            'events' => count($codes),
            'imported' => $stats['imported'],
            'changed' => $stats['changed'] + $deleted,
            'unchanged' => $stats['unchanged'],
            'cursor' => null,
        ];
    }
}
