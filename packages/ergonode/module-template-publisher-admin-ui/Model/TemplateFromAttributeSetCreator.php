<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisherAdminUi\Model;

use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Template\Api\ProductAttributeSetProviderInterface;
use Ergonode\Template\Api\PublishedTemplateRecorderInterface;
use Ergonode\TemplatePublisher\Api\TemplateCreatorInterface;
use Magento\Framework\Exception\LocalizedException;

class TemplateFromAttributeSetCreator
{
    public function __construct(
        private readonly ProductAttributeSetProviderInterface $attributeSetProvider,
        private readonly TemplateCreatorInterface $templateCreator,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly PublishedTemplateRecorderInterface $templateRecorder
    ) {
    }

    /**
     * @return array{code: string, name: string}
     */
    public function create(string $templateCode, int $attributeSetId): array
    {
        $attributeSetName = $this->getAttributeSetName($attributeSetId);
        $languageCode = $this->languageMappingProvider->requireAdminLanguageCode();

        $this->templateCreator->create($templateCode, [$languageCode => $attributeSetName]);
        $this->templateRecorder->record($templateCode, [$languageCode => $attributeSetName]);

        return [
            'code' => trim($templateCode),
            'name' => $attributeSetName,
        ];
    }

    private function getAttributeSetName(int $attributeSetId): string
    {
        foreach ($this->attributeSetProvider->getProductAttributeSets() as $attributeSet) {
            if ((int)$attributeSet['id'] === $attributeSetId) {
                return (string)$attributeSet['name'];
            }
        }

        throw new LocalizedException(__('Invalid Magento attribute set identifier "%1".', $attributeSetId));
    }
}
