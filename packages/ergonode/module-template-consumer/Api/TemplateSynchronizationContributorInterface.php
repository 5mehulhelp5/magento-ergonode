<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Api;

interface TemplateSynchronizationContributorInterface
{
    /**
     * Synchronize Magento only after a complete source snapshot has been imported.
     * Deleted codes include earlier deletions so interrupted cleanup can be retried.
     *
     * @param string[] $templateCodes
     * @param string[] $deletedTemplateCodes
     * @return void
     */
    public function execute(array $templateCodes, array $deletedTemplateCodes = []): void;
}
