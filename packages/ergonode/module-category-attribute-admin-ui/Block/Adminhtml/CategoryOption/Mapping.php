<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryOption;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingProvider;
use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingCapabilities;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;

class Mapping extends Template
{
    /** @var array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>}|null */
    private ?array $context = null;

    public function __construct(
        Context $context,
        private readonly MappingProvider $managementProvider,
        private readonly Json $json,
        private readonly FormKey $formKeyProvider,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly MappingCapabilities $capabilities,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<string, mixed> */
    public function getAttributeContext(): array
    {
        return $this->getSelectedContext() ?? [
            'left' => ['label' => __('No mapped category attribute pair'), 'code' => '', 'type' => 'select'],
            'right' => ['label' => __('No mapped category attribute pair'), 'code' => '', 'type' => 'select'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getAttributeContexts(): array
    {
        return $this->getContextData()['contexts'];
    }

    /** @return array<int, array<string, mixed>> */
    public function getErgonodeOptions(): array
    {
        $context = $this->getSelectedContext();

        return $context ? $this->managementProvider->getErgonodeOptions((string)$context['left']['code']) : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getMagentoOptions(): array
    {
        $context = $this->getSelectedContext();

        return $context ? $this->managementProvider->getMagentoOptions((string)$context['right']['code']) : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getDraftMappings(): array
    {
        return array_values(array_filter(
            $this->getMappings(),
            static fn (array $mapping): bool => empty($mapping['left']) || empty($mapping['right'])
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function getMappedOptions(): array
    {
        return array_values(array_filter(
            $this->getMappings(),
            static fn (array $mapping): bool => !empty($mapping['left']) && !empty($mapping['right'])
        ));
    }

    public function getMappingConfigJson(): string
    {
        return $this->json->serialize(array_replace_recursive([
            'urls' => [
                'auto_match' => $this->getUrl('ergonode/category_option/autoMatch'),
                'save' => $this->getUrl('ergonode/category_option/save'),
                'context' => $this->getUrl(
                    'ergonode/category_option/index',
                    ['mapping_id' => '__mapping_id__']
                ),
            ],
            'attribute_type_compatibility' => $this->typeCompatibility->getAttributeCompatibilityMap(),
            'form_key' => $this->formKeyProvider->getFormKey(),
            'attribute_mapping_id' => (int)($this->getAttributeContext()['mapping_id'] ?? 0),
        ], $this->capabilities->getConfig('option')));
    }

    public function canCreateMagentoOptions(): bool
    {
        return (bool)($this->capabilities->getConfig('option')['allow_magento_option_creation'] ?? false);
    }

    public function canSynchronizeOptions(): bool
    {
        return false;
    }

    public function getWorkspaceId(): string
    {
        return $this->getData('embedded')
            ? 'ergonode-category-option-mapping-modal'
            : 'ergonode-category-option-mapping';
    }

    public function getCurrentNavigationSection(): string
    {
        return 'category_options';
    }

    public function getPairTemplate(): string
    {
        return 'Ergonode_CoreAdminUi::option/pair.phtml';
    }

    /** @return array<string, mixed>|null */
    private function getSelectedContext(): ?array
    {
        $context = $this->getContextData()['context'];

        return is_array($context) ? $context : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function getMappings(): array
    {
        $context = $this->getSelectedContext();
        if (!$context) {
            return [];
        }

        return $this->managementProvider->getOptionMappings(
            (int)$context['mapping_id'],
            (string)$context['left']['code'],
            (string)$context['right']['code']
        );
    }

    /** @return array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>} */
    private function getContextData(): array
    {
        if ($this->context !== null) {
            return $this->context;
        }

        $mappingId = (int)$this->getRequest()->getParam('mapping_id');

        return $this->context = $this->managementProvider->getOptionContext($mappingId > 0 ? $mappingId : null);
    }
}
