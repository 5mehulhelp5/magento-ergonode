<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumer\Model\Import;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateAttributeConsumer\Model\Config\TemplateAttributeConfigProvider;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateAttributeSetSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\MagentoTemplateStructureSyncer;
use Ergonode\TemplateAttributeConsumer\Model\Sync\TemplateStructureCleaner;
use Ergonode\TemplateAttributeConsumer\Model\Template\TemplateCacheProvider;
use Ergonode\TemplateConsumer\Api\TemplateSynchronizationContributorInterface;
use Magento\Framework\Exception\LocalizedException;

class ImportedTemplateSynchronizer implements TemplateSynchronizationContributorInterface
{
    public function __construct(
        private readonly TemplateAttributeConfigProvider $configProvider,
        private readonly TemplateCacheProvider $templateCacheProvider,
        private readonly MagentoTemplateStructureSyncer $templateStructureSyncer,
        private readonly MagentoTemplateAttributeSetSyncer $templateAttributeSetSyncer,
        private readonly ChangeReport $changeReport,
        private readonly TemplateStructureCleaner $structureCleaner
    ) {
    }

    /**
     * @param string[] $templateCodes
     * @param string[] $deletedTemplateCodes
     * @throws LocalizedException
     */
    public function execute(array $templateCodes, array $deletedTemplateCodes = []): void
    {
        if (!$this->configProvider->shouldSyncAttributes()) {
            return;
        }

        foreach (array_values(array_unique($templateCodes)) as $templateCode) {
            $template = $this->templateCacheProvider->getTemplateStructure($templateCode);
            if ($template === null || (int)($template['attribute_set_id'] ?? 0) <= 0) {
                $this->changeReport->add(
                    'template_structure',
                    $templateCode,
                    ChangeReport::ACTION_SKIPPED,
                    'Skipped template structure sync because Magento attribute set is not assigned.',
                    []
                );
                continue;
            }

            if ($this->configProvider->shouldSyncSections()) {
                $this->templateStructureSyncer->syncTemplate($templateCode, null, false, true);
            } else {
                $this->templateAttributeSetSyncer->syncTemplate($templateCode, false, true);
            }
        }
        foreach (array_values(array_unique($deletedTemplateCodes)) as $templateCode) {
            $this->structureCleaner->removeTemplate($templateCode);
        }
    }
}
