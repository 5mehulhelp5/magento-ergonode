<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Sync;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;
use Magento\Framework\Exception\LocalizedException;

class TemplateAttributeSetResolver
{
    public function __construct(
        private readonly TemplateCacheProvider $templateCacheProvider,
        private readonly AttributeSetManager $attributeSetManager,
        private readonly ChangeReport $changeReport,
        private readonly TemplateConfigProvider $configProvider
    ) {
    }

    /**
     * @param array{code: string, attribute_set_id: int|null} $template
     * @param array<string, int> $stats
     */
    public function resolveForSync(
        array $template,
        ?int $requestedAttributeSetId,
        bool $createAttributeSet,
        array &$stats
    ): int {
        $templateCode = (string)$template['code'];
        $currentAttributeSetId = (int)($template['attribute_set_id'] ?? 0);

        if ($requestedAttributeSetId !== null) {
            $this->attributeSetManager->validateProductAttributeSet($requestedAttributeSetId);
            if ($currentAttributeSetId !== $requestedAttributeSetId) {
                $this->templateCacheProvider->assignAttributeSet($templateCode, $requestedAttributeSetId);
                $stats['attribute_sets_assigned']++;
                $this->changeReport->add(
                    'template_attribute_set',
                    $templateCode,
                    ChangeReport::ACTION_UPDATED,
                    'Assigned existing Magento attribute set to Ergonode template.',
                    [
                        'old_attribute_set_id' => $currentAttributeSetId ?: null,
                        'attribute_set_id' => $requestedAttributeSetId,
                    ]
                );
            } else {
                $stats['attribute_sets_unchanged']++;
            }

            return $requestedAttributeSetId;
        }

        if ($currentAttributeSetId > 0) {
            $this->attributeSetManager->validateProductAttributeSet($currentAttributeSetId);
            $stats['attribute_sets_unchanged']++;
            return $currentAttributeSetId;
        }

        $this->assertCreationAllowed($templateCode, $createAttributeSet);
        $result = $this->attributeSetManager->createOrGetForTemplate($templateCode);
        $attributeSetId = (int)$result['attribute_set_id'];
        $this->attributeSetManager->validateProductAttributeSet($attributeSetId);
        $this->templateCacheProvider->assignAttributeSet($templateCode, $attributeSetId);
        $stats[$result['created'] ? 'attribute_sets_created' : 'attribute_sets_unchanged']++;
        $stats['attribute_sets_assigned']++;

        return $attributeSetId;
    }

    private function assertCreationAllowed(string $templateCode, bool $createAttributeSet): void
    {
        if (!$createAttributeSet) {
            throw new LocalizedException(
                __('Magento attribute set is not assigned for Ergonode template "%1".', $templateCode)
            );
        }
        if (!$this->configProvider->shouldCreateAttributeSets()) {
            throw new LocalizedException(
                __('Automatic Magento attribute set creation is disabled in Ergonode configuration.')
            );
        }
    }
}
