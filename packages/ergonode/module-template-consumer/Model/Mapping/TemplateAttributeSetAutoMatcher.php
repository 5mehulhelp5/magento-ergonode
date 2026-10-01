<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumer\Model\Mapping;

use Ergonode\Core\Model\Report\ChangeReport;
use Ergonode\Template\Model\TemplateCodeNormalizer;
use Ergonode\TemplateConsumer\Model\Config\TemplateConfigProvider;
use Ergonode\TemplateConsumer\Model\Sync\AttributeSetManager;
use Ergonode\TemplateConsumer\Model\Template\TemplateCacheProvider;

class TemplateAttributeSetAutoMatcher
{
    public function __construct(
        private readonly TemplateConfigProvider $configProvider,
        private readonly TemplateCodeNormalizer $codeNormalizer,
        private readonly AttributeSetManager $attributeSetManager,
        private readonly TemplateCacheProvider $templateCacheProvider,
        private readonly ChangeReport $changeReport
    ) {
    }

    /**
     * @param string[] $templateCodes
     * @return array{matched: int, conflicts: int}
     */
    public function match(array $templateCodes): array
    {
        $stats = ['matched' => 0, 'conflicts' => 0];
        if (!$this->configProvider->shouldCreateAttributeSets()) {
            return $stats;
        }

        $templates = $this->templatesByCode();
        $usedAttributeSetIds = [];
        foreach ($templates as $template) {
            $attributeSetId = (int)($template['attribute_set_id'] ?? 0);
            if ($attributeSetId > 0) {
                $usedAttributeSetIds[$attributeSetId] = true;
            }
        }
        $candidates = $this->attributeSetCandidates($usedAttributeSetIds);

        foreach (array_values(array_unique($templateCodes)) as $templateCode) {
            $template = $templates[$templateCode] ?? null;
            if ($template === null
                || !empty($template['is_deleted'])
                || (int)($template['attribute_set_id'] ?? 0) > 0
            ) {
                continue;
            }

            $matches = $candidates[$templateCode] ?? [];
            if (count($matches) !== 1) {
                if (count($matches) > 1) {
                    $stats['conflicts']++;
                    $this->changeReport->add(
                        'template_attribute_set',
                        $templateCode,
                        ChangeReport::ACTION_SKIPPED,
                        'Skipped automatic attribute set mapping because normalized Magento names are ambiguous.',
                        ['attribute_set_ids' => array_column($matches, 'id')]
                    );
                }
                continue;
            }

            $attributeSet = $matches[0];
            $this->templateCacheProvider->assignAttributeSet($templateCode, (int)$attributeSet['id']);
            $usedAttributeSetIds[(int)$attributeSet['id']] = true;
            unset($candidates[$templateCode]);
            $stats['matched']++;
            $this->changeReport->add(
                'template_attribute_set',
                $templateCode,
                ChangeReport::ACTION_UPDATED,
                'Automatically mapped Magento attribute set by normalized name.',
                [
                    'attribute_set_id' => (int)$attributeSet['id'],
                    'attribute_set_name' => (string)$attributeSet['name'],
                ]
            );
        }

        return $stats;
    }

    /** @return array<string, array<string, int|string|bool|null>> */
    private function templatesByCode(): array
    {
        $templates = [];
        foreach ($this->templateCacheProvider->getAllTemplates(true) as $template) {
            $templates[(string)$template['code']] = $template;
        }

        return $templates;
    }

    /**
     * @param array<int, true> $usedAttributeSetIds
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    private function attributeSetCandidates(array $usedAttributeSetIds): array
    {
        $candidates = [];
        foreach ($this->attributeSetManager->getProductAttributeSets() as $attributeSet) {
            $attributeSetId = (int)$attributeSet['id'];
            if (isset($usedAttributeSetIds[$attributeSetId])) {
                continue;
            }
            $normalizedName = $this->codeNormalizer->normalize((string)$attributeSet['name']);
            if ($normalizedName !== '') {
                $candidates[$normalizedName][] = $attributeSet;
            }
        }

        return $candidates;
    }
}
