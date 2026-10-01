<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisherAdminUi\Block\Adminhtml;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\Language\Api\LanguageStoreMappingProviderInterface;
use Ergonode\Language\Exception\NoActiveLanguageMappingException;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class AttributePublisherMapping extends Template
{
    public function __construct(
        Context $context,
        private readonly Json $json,
        private readonly LanguageStoreMappingProviderInterface $languageMappingProvider,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize(
            [
            'mode' => (string)$this->getData('mode'),
            'entity_kind' => (string)($this->getData('entity_kind') ?: $this->getData('mode')),
            'batch_size' => 20,
            'attribute_compatibility' => $this->typeCompatibility->getAttributeCompatibilityMap(),
            'form_key' => $this->getFormKey(),
            'urls' => [
                'batch_create' => $this->getUrl($this->getBatchRoute()),
            ],
            'language_mapping' => $this->getLanguageMappingConfig(),
            ]
        );
    }

    public function getTargetSelector(): string
    {
        return (string)$this->getData('target_selector');
    }

    private function getBatchRoute(): string
    {
        $route = trim((string)$this->getData('batch_route'));
        if ($route !== '') {
            return $route;
        }

        return (string)$this->getData('mode') === 'option'
            ? 'ergonode_attribute_publish/option_batch/create'
            : 'ergonode_attribute_publish/attribute_batch/create';
    }

    /**
     * @return array{active: bool, message: string, url: string}
     */
    private function getLanguageMappingConfig(): array
    {
        try {
            $this->languageMappingProvider->getLanguageCodes();

            return [
                'active' => true,
                'message' => '',
                'url' => $this->getUrl('ergonode/language/index'),
            ];
        } catch (NoActiveLanguageMappingException $exception) {
            return [
                'active' => false,
                'message' => $exception->getMessage(),
                'url' => $this->getUrl('ergonode/language/index'),
            ];
        }
    }
}
