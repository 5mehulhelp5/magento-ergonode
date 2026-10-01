<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;

class TemplateSnapshotReconciler
{
    public function __construct(
        private readonly TemplateCacheWriter $cacheWriter,
        private readonly ChangeReport $changeReport
    ) {
    }

    /** @param string[] $activeTemplateCodes */
    public function reconcile(array $activeTemplateCodes): int
    {
        $deletedTemplateCodes = $this->cacheWriter->markMissingAsDeleted($activeTemplateCodes);
        foreach ($deletedTemplateCodes as $templateCode) {
            $this->changeReport->add(
                'template',
                $templateCode,
                ChangeReport::ACTION_UPDATED,
                'Template is absent from the completed Ergonode templateList scan.',
                ['deleted' => true]
            );
        }

        return count($deletedTemplateCodes);
    }

    /** @return string[] */
    public function getDeletedCodes(): array
    {
        return $this->cacheWriter->getDeletedCodes();
    }
}
