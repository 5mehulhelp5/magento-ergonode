<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;
use Magento\Framework\Exception\LocalizedException;

class TemplateAttributeSetSynchronizer
{
    public function __construct(
        private readonly TemplateConfigProvider $configProvider,
        private readonly TemplateCacheProvider $templateCacheProvider,
        private readonly AttributeSetManager $attributeSetManager,
        private readonly ChangeReport $changeReport
    ) {
    }

    /**
     * @param string[] $templateCodes
     * @throws LocalizedException
     */
    public function sync(array $templateCodes): void
    {
        foreach (array_values(array_unique($templateCodes)) as $templateCode) {
            $template = $this->templateCacheProvider->getTemplate($templateCode);
            if ($template === null) {
                continue;
            }

            $attributeSetId = (int)($template['attribute_set_id'] ?? 0);
            if ($attributeSetId > 0) {
                if ($this->templateCacheProvider->clearStaleAttributeSetMapping($templateCode, $attributeSetId)) {
                    $this->changeReport->add(
                        'template_attribute_set',
                        $templateCode,
                        ChangeReport::ACTION_UPDATED,
                        'Cleared stale Magento attribute set mapping during template import.',
                        ['lost_attribute_set_id' => $attributeSetId]
                    );
                } else {
                    continue;
                }
            }

            if (!$this->configProvider->shouldCreateAttributeSets()) {
                continue;
            }

            $createdAttributeSet = $this->attributeSetManager->createOrGetForTemplate($templateCode);
            $this->templateCacheProvider->assignAttributeSet(
                $templateCode,
                (int)$createdAttributeSet['attribute_set_id']
            );
        }
    }
}
