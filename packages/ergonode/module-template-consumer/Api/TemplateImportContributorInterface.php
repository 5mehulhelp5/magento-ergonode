<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Api;

interface TemplateImportContributorInterface
{
    /**
     * Enrich already persisted template snapshots without changing Magento
     * attribute sets, groups, placements or template-to-attribute-set mappings.
     *
     * @param string[] $templateCodes
     * @return void
     */
    public function execute(array $templateCodes): void;
}
