<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Block\Adminhtml\Option;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use Ergonode\ProductAttributeAdminUi\Model\Mapping\OptionMappingProvider;
use Ergonode\ProductAttributeAdminUi\Api\ErgonodeMetadataProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoOptionProvider;
use Ergonode\ProductAttributeAdminUi\Model\AttributeListSorter;
use Ergonode\CoreAdminUi\Block\Adminhtml\SectionNavigation;

class Mapping extends Template
{
    private readonly AttributeListSorter $attributeListSorter;

    /**
     * @var array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>}|null
     */
    private ?array $contextDataCache = null;

    /**
     * @var array<int, array{left: array<string, string>|null, right: array<string, string>|null, tone?: string}>|null
     */
    private ?array $mappingsCache = null;

    /**
     * @var array<int, array{label: string, code: string, scope: string, type: string, active: bool}>|null
     */
    private ?array $ergonodeOptionsCache = null;

    /**
     * @var array<int, array{label: string, code: string, scope: string, type: string, active: bool}>|null
     */
    private ?array $magentoOptionsCache = null;

    public function __construct(
        Context $context,
        private readonly OptionMappingProvider $optionMappingProvider,
        private readonly ErgonodeMetadataProviderInterface $ergonodeOptionProvider,
        private readonly MagentoOptionProvider $magentoOptionProvider,
        private readonly Json $json,
        private readonly FormKey $mappingFormKey,
        ?AttributeListSorter $attributeListSorter = null,
        array $data = [],
        bool $embedded = false
    ) {
        $this->attributeListSorter = $attributeListSorter ?? new AttributeListSorter();
        $data['template'] = 'Ergonode_CoreAdminUi::option/mapping.phtml';
        if ($embedded) {
            $data['embedded'] = true;
        }
        parent::__construct($context, $data);
    }

    /**
     * @return array{
     *     left: array<string, string>,
     *     right: array<string, string>,
     *     mapping_id?: int,
     *     magento_has_custom_source: bool
     * }
     */
    public function getAttributeContext(): array
    {
        $context = $this->getSelectedContext();

        if (!$context) {
            return [
                'left' => ['label' => __('No mapped attribute pair'), 'code' => '', 'type' => 'select'],
                'right' => ['label' => __('No mapped attribute pair'), 'code' => '', 'type' => 'select'],
                'magento_has_custom_source' => false,
            ];
        }

        return [
            'mapping_id' => (int)$context['mapping_id'],
            'left' => $context['left'],
            'right' => $context['right'],
            'magento_has_custom_source' => !empty($context['right']['has_custom_source']),
        ];
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     mapping_id: int,
     *     left: array<string, string>,
     *     right: array<string, string>
     * }>
     */
    public function getAttributeContexts(): array
    {
        return $this->getContextData()['contexts'];
    }

    /**
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    public function getErgonodeOptions(): array
    {
        if ($this->ergonodeOptionsCache !== null) {
            return $this->ergonodeOptionsCache;
        }

        $context = $this->getSelectedContext();

        return $this->ergonodeOptionsCache = $context
            ? $this->attributeListSorter->sortByLabel(
                $this->ergonodeOptionProvider->getOptions((string)$context['left']['code'])
            )
            : [];
    }

    /**
     * @return array<int, array{label: string, code: string, scope: string, type: string, active: bool}>
     */
    public function getMagentoOptions(): array
    {
        if ($this->magentoOptionsCache !== null) {
            return $this->magentoOptionsCache;
        }

        $context = $this->getSelectedContext();

        return $this->magentoOptionsCache = $context
            ? $this->attributeListSorter->sortByLabel(
                $this->magentoOptionProvider->getOptions((string)$context['right']['code'])
            )
            : [];
    }

    /**
     * @return array<int, array{left: array<string, string>|null, right: array<string, string>|null, tone?: string}>
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
     * @return array<int, array{left: array<string, string>, right: array<string, string>, tone?: string}>
     */
    public function getMappedOptions(): array
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
                    ? $this->getUrl('ergonode/option/refresh')
                    : $this->getUrl('ergonode/option/fetch'),
                'sync' => $this->getData('sync_route') ? $this->getUrl((string)$this->getData('sync_route')) : '',
                'auto_match' => $this->getUrl('ergonode/option/autoMatch'),
                'save' => $this->getUrl('ergonode/option/save'),
                'delete_snapshot' => $this->getData('snapshot_available')
                    ? $this->getUrl('ergonode/option/deleteSnapshot') : '',
                'context' => $this->getUrl('ergonode/option/index', ['mapping_id' => '__mapping_id__']),
            ],
            'form_key' => $this->mappingFormKey->getFormKey(),
            'attribute_mapping_id' => (int)($this->getAttributeContext()['mapping_id'] ?? 0),
            ]
        );
    }

    public function canCreateMagentoOptions(): bool
    {
        return (bool)$this->getData('allow_magento_creation');
    }

    public function canSynchronizeOptions(): bool
    {
        $resource = (string)$this->getData('sync_resource');

        return $resource !== '' && $this->getAuthorization()->isAllowed($resource);
    }

    public function getWorkspaceId(): string
    {
        return $this->getData('embedded') ? 'ergonode-option-mapping-modal' : 'ergonode-option-mapping';
    }

    public function getCurrentNavigationSection(): string
    {
        return SectionNavigation::SECTION_OPTIONS;
    }

    public function getPairTemplate(): string
    {
        return 'Ergonode_CoreAdminUi::option/pair.phtml';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSelectedContext(): ?array
    {
        $data = $this->getContextData();

        return isset($data['context']) && is_array($data['context']) ? $data['context'] : null;
    }

    /**
     * @return array<int, array{left: array<string, string>|null, right: array<string, string>|null, tone?: string}>
     */
    private function getMappings(): array
    {
        if ($this->mappingsCache !== null) {
            return $this->mappingsCache;
        }

        $context = $this->getSelectedContext();

        if (!$context) {
            return $this->mappingsCache = [];
        }

        return $this->mappingsCache = $this->optionMappingProvider->getMappings(
            (int)$context['mapping_id'],
            (string)$context['left']['code'],
            (string)$context['right']['code']
        );
    }

    private function getRequestedMappingId(): ?int
    {
        $mappingId = (int)$this->getRequest()->getParam('mapping_id');

        return $mappingId > 0 ? $mappingId : null;
    }

    /**
     * @return array{context: array<string, mixed>|null, contexts: array<int, array<string, mixed>>}
     */
    private function getContextData(): array
    {
        if ($this->contextDataCache !== null) {
            return $this->contextDataCache;
        }

        return $this->contextDataCache = $this->optionMappingProvider->getContext($this->getRequestedMappingId());
    }
}
