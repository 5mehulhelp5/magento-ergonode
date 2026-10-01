<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Block\Adminhtml\Attribute;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\AttributeMappingProvider;
use Ergonode\ProductAttribute\Model\Mapping\ProductAttributeMappingCompatibility;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use Ergonode\ProductAttributeAdminUi\Model\AttributeListSorter;
use Ergonode\CoreAdminUi\Block\Adminhtml\SectionNavigation;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;

class Mapping extends Template
{
    /**
     * @var array<int, array{
     *     mapping_id?: int,
     *     left: array{label: string, code: string, type: string, scope: string, active?: bool}|null,
     *     right: array{
     *         label: string,
     *         code: string,
     *         type: string,
     *         scope: string,
     *         active?: bool,
     *         required?: bool,
     *         has_custom_source?: bool
     *     }|null,
     *     tone?: string,
     *     option_progress?: array{mapped: int, total: int},
     *     option_url?: string,
     *     option_modal_url?: string
     * }>|null
     */
    private ?array $mappingsCache = null;

    public function __construct(
        Context $context,
        private readonly ErgonodeMetadataProviderInterface $ergonodeAttributeProvider,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly AttributeMappingProvider $attributeMappingProvider,
        private readonly Json $json,
        private readonly FormKey $mappingFormKey,
        private readonly AttributeListSorter $attributeListSorter,
        private readonly ProductAttributeMappingCompatibility $productAttributeMappingCompatibility,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    public function getErgonodeAttributes(): array
    {
        return $this->attributeListSorter->sortByLabel(
            array_filter(
                $this->ergonodeAttributeProvider->getAttributes(),
                static fn (array $attribute): bool => strtolower(trim((string)($attribute['type'] ?? '')))
                    !== 'gallery'
            )
        );
    }

    /**
     * @return array<int, array{
     *     label: string,
     *     code: string,
     *     scope: string,
     *     type: string,
     *     active: bool,
     *     required: bool,
     *     has_custom_source: bool
     * }>
     */
    public function getMagentoAttributes(): array
    {
        return $this->attributeListSorter->sortByLabel(
            $this->magentoAttributeProvider->getAttributes()
        );
    }

    /**
     * @return string[]
     */
    public function getExistingMagentoAttributeCodes(): array
    {
        return array_keys($this->magentoAttributeProvider->getAttributeMap(true));
    }

    public function canCreateMagentoAttributes(): bool
    {
        return (bool)$this->getData('allow_magento_creation');
    }

    /**
     * @return array<int, array{
     *     left: array{label: string, code: string, type: string, scope: string, active?: bool}|null,
     *     right: array{
     *         label: string,
     *         code: string,
     *         type: string,
     *         scope: string,
     *         active?: bool,
     *         required?: bool,
     *         has_custom_source?: bool
     *     }|null,
     *     tone?: string
     * }>
     */
    public function getDraftMappings(): array
    {
        return array_values(
            array_filter(
                $this->getMappings(),
                static fn (array $mapping): bool => empty($mapping['left']) || empty($mapping['right'])
            )
        );
    }

    /**
     * @return array<int, array{
     *     mapping_id?: int,
     *     left: array{label: string, code: string, type: string, scope: string, active?: bool},
     *     right: array{
     *         label: string,
     *         code: string,
     *         type: string,
     *         scope: string,
     *         active?: bool,
     *         required?: bool,
     *         has_custom_source?: bool
     *     },
     *     tone?: string,
     *     option_progress?: array{mapped: int, total: int},
     *     option_url?: string,
     *     option_modal_url?: string
     * }>
     */
    public function getMappedAttributes(): array
    {
        return array_values(
            array_filter(
                $this->getMappings(),
                static fn (array $mapping): bool => !empty($mapping['left']) && !empty($mapping['right'])
            )
        );
    }

    public function getMappingConfigJson(): string
    {
        return $this->json->serialize(
            [
            'urls' => [
                'refresh' => $this->getData('snapshot_available')
                    ? $this->getUrl('ergonode/attribute/refresh')
                    : $this->getUrl('ergonode/attribute/fetch'),
                'sync' => $this->getData('sync_route') ? $this->getUrl((string)$this->getData('sync_route')) : '',
                'auto_match' => $this->getUrl('ergonode/attribute/autoMatch'),
                'save' => $this->getUrl('ergonode/attribute/save'),
                'delete_snapshot' => $this->getData('snapshot_available')
                    ? $this->getUrl('ergonode/attribute/deleteSnapshot') : '',
                'language_mapping' => $this->getUrl('ergonode/language/index'),
            ],
            'attribute_type_compatibility' => $this->productAttributeMappingCompatibility
                ->getAttributeCompatibilityMap(),
            'magento_attribute_type_constraints' => $this->productAttributeMappingCompatibility
                ->getMagentoAttributeTypeConstraints(),
            'allow_magento_attribute_creation' => $this->canCreateMagentoAttributes(),
            'existing_magento_attribute_codes' => $this->getExistingMagentoAttributeCodes(),
            'form_key' => $this->mappingFormKey->getFormKey(),
            ]
        );
    }

    public function canSynchronizeAttributes(): bool
    {
        $resource = (string)$this->getData('sync_resource');

        return $resource !== '' && $this->getAuthorization()->isAllowed($resource);
    }

    /**
     * @param array<string, mixed> $mapping
     */
    public function isTypeCompatible(array $mapping): bool
    {
        $left = isset($mapping['left']) && is_array($mapping['left']) ? $mapping['left'] : null;
        $right = isset($mapping['right']) && is_array($mapping['right']) ? $mapping['right'] : null;
        if ($left === null || $right === null) {
            return true;
        }

        return $this->productAttributeMappingCompatibility->canMapAttributes(
            (string)($left['type'] ?? ''),
            (string)($right['type'] ?? ''),
            (string)($right['code'] ?? '')
        );
    }

    public function getWorkspaceId(): string
    {
        return 'ergonode-attribute-mapping';
    }

    public function getPairTemplate(): string
    {
        return 'Ergonode_CoreAdminUi::attribute/pair.phtml';
    }

    public function getCurrentNavigationSection(): string
    {
        return SectionNavigation::SECTION_ATTRIBUTES;
    }

    /**
     * @return array<int, array{
     *     mapping_id?: int,
     *     left: array{label: string, code: string, type: string, scope: string, active?: bool}|null,
     *     right: array{
     *         label: string,
     *         code: string,
     *         type: string,
     *         scope: string,
     *         active?: bool,
     *         required?: bool,
     *         has_custom_source?: bool
     *     }|null,
     *     tone?: string,
     *     option_progress?: array{mapped: int, total: int},
     *     option_url?: string,
     *     option_modal_url?: string
     * }>
     */
    private function getMappings(): array
    {
        if ($this->mappingsCache !== null) {
            return $this->mappingsCache;
        }

        $mappings = $this->attributeMappingProvider->getMappings();
        $optionProgress = $this->attributeMappingProvider->getOptionMappingProgress();

        foreach ($mappings as &$mapping) {
            $mappingId = (int)($mapping['mapping_id'] ?? 0);
            if ($mappingId <= 0 || !isset($optionProgress[$mappingId])) {
                continue;
            }

            $mapping['option_progress'] = $optionProgress[$mappingId];
            $mapping['option_url'] = $this->getUrl(
                'ergonode/option/index',
                ['mapping_id' => $mappingId]
            );
            $mapping['option_modal_url'] = $this->getUrl(
                'ergonode/option/modal',
                ['mapping_id' => $mappingId]
            );
        }
        unset($mapping);

        return $this->mappingsCache = $mappings;
    }
}
