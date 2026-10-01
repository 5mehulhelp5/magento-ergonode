<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryAttribute;

use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingProvider;
use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\CategoryAttributeAdminUi\Model\Mapping\MappingCapabilities;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;

class Mapping extends Template
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $mappings = null;

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

    /** @return array<int, array<string, mixed>> */
    public function getErgonodeAttributes(): array
    {
        return $this->sort($this->managementProvider->getErgonodeAttributes());
    }

    /** @return array<int, array<string, mixed>> */
    public function getMagentoAttributes(): array
    {
        return $this->sort($this->managementProvider->getMagentoAttributes());
    }

    public function canCreateMagentoAttributes(): bool
    {
        return false;
    }

    public function canSynchronizeAttributes(): bool
    {
        return false;
    }

    /** @param array<string, mixed> $mapping */
    public function isTypeCompatible(array $mapping): bool
    {
        // The mapping provider already evaluates category attribute type compatibility.
        return ($mapping['tone'] ?? 'warning') !== 'error';
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
    public function getMappedAttributes(): array
    {
        $mappings = array_values(array_filter(
            $this->getMappings(),
            static fn (array $mapping): bool => !empty($mapping['left']) && !empty($mapping['right'])
        ));
        $progress = $this->managementProvider->getOptionMappingProgress();
        foreach ($mappings as &$mapping) {
            $mappingId = (int)($mapping['mapping_id'] ?? 0);
            if (!isset($progress[$mappingId])) {
                continue;
            }
            $mapping['option_progress'] = $progress[$mappingId];
            $mapping['option_url'] = $this->getUrl('ergonode/category_option/index', ['mapping_id' => $mappingId]);
            $mapping['option_modal_url'] = $this->getUrl(
                'ergonode/category_option/modal',
                ['mapping_id' => $mappingId]
            );
        }
        unset($mapping);

        return $mappings;
    }

    public function getMappingConfigJson(): string
    {
        return $this->json->serialize(array_replace_recursive([
            'urls' => [
                'save' => $this->getUrl('ergonode/category_attribute/save'),
                'language_mapping' => $this->getUrl('ergonode/language/index'),
            ],
            'allow_magento_attribute_creation' => $this->canCreateMagentoAttributes(),
            'attribute_type_compatibility' => $this->typeCompatibility->getAttributeCompatibilityMap(),
            'form_key' => $this->formKeyProvider->getFormKey(),
        ], $this->capabilities->getConfig('attribute')));
    }

    public function getWorkspaceId(): string
    {
        return 'ergonode-category-attribute-mapping';
    }

    public function getPairTemplate(): string
    {
        return 'Ergonode_CoreAdminUi::attribute/pair.phtml';
    }

    public function getCurrentNavigationSection(): string
    {
        return 'category_attributes';
    }

    /** @return array<int, array<string, mixed>> */
    private function getMappings(): array
    {
        return $this->mappings ??= $this->managementProvider->getAttributeMappings();
    }

    /**
     * @param array<int, array<string, mixed>> $attributes
     * @return array<int, array<string, mixed>>
     */
    private function sort(array $attributes): array
    {
        usort($attributes, static fn (array $first, array $second): int => strnatcasecmp(
            (string)($first['label'] ?? $first['code'] ?? ''),
            (string)($second['label'] ?? $second['code'] ?? '')
        ));

        return $attributes;
    }
}
