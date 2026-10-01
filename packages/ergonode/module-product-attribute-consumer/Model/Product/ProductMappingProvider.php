<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Product;

use Ergonode\Core\Model\Report\ChangeReport;

use Ergonode\AttributeConsumer\Api\AttributeAvailabilityInterface;

use Ergonode\ProductAttributeConsumer\Api\ProductAttributeMappingProviderInterface;
use Ergonode\ProductAttribute\Api\CompleteMappingProviderInterface;
use Ergonode\ProductAttribute\Model\Provider\MagentoAttributeProvider;
use InvalidArgumentException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;

class ProductMappingProvider implements ProductAttributeMappingProviderInterface
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $mappings = null;

    public function __construct(
        private readonly ChangeReport $changeReport,
        private readonly AttributeAvailabilityInterface $availability,
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly MagentoAttributeProvider $magentoAttributeProvider,
        private readonly CompleteMappingProviderInterface $mappingProvider
    ) {
    }

    /**
     * @return array<int, array{
     *     mapping_id: int,
     *     ergonode_attribute_code: string,
     *     magento_attribute_code: string,
     *     ergonode_type: string,
     *     magento_type: string,
     *     option_ids: array<string, int>,
     *     option_labels: array<string, array<string, string>>,
     *     magento_option_ids_by_label: array<string, int>,
     *     magento_has_custom_source: bool,
     *     magento_source_option_values: array<int, int|string>
     * }>
     */
    public function getMappings(): array
    {
        if ($this->mappings !== null) {
            return $this->mappings;
        }

        $rows = $this->mappingProvider->getMappings('import');
        $mappings = [];
        $attributeCodes = [];
        $magentoOptionAttributeCodes = [];

        $available = array_fill_keys($this->availability->getCodes(), true);
        foreach ($rows as $row) {
            if (!isset($available[(string)$row['ergonode_attribute_code']])) {
                $this->changeReport->add(
                    'attribute',
                    (string)$row['ergonode_attribute_code'],
                    ChangeReport::ACTION_SKIPPED,
                    'Product mapping skipped: Ergonode attribute definition is unavailable. Magento values preserved.'
                );
                continue;
            }
            $mappingId = (int)$row['mapping_id'];
            $attributeCode = (string)$row['ergonode_attribute_code'];
            $magentoAttributeCode = (string)$row['magento_attribute_code'];
            $magentoType = (string)$row['magento_type'];
            $magentoAttribute = $this->magentoAttributeProvider->getAttribute($magentoAttributeCode, true);
            $hasCustomSource = !empty($magentoAttribute['has_custom_source']);
            $mappings[$mappingId] = [
                'mapping_id' => $mappingId,
                'ergonode_attribute_code' => $attributeCode,
                'magento_attribute_code' => $magentoAttributeCode,
                'ergonode_type' => (string)$row['ergonode_type'],
                'magento_type' => $magentoType,
                'option_ids' => $row['option_ids'],
                'option_labels' => [],
                'magento_option_ids_by_label' => [],
                'magento_has_custom_source' => $hasCustomSource,
                'magento_source_option_values' => $hasCustomSource
                    ? $this->magentoAttributeProvider->getCustomSourceOptionValues($magentoAttributeCode)
                    : [],
            ];
            $attributeCodes[] = $attributeCode;
            if (!$hasCustomSource && in_array($magentoType, ['select', 'multiselect'], true)) {
                $magentoOptionAttributeCodes[] = $magentoAttributeCode;
            }
        }

        $this->attachOptionLabels($mappings, array_values(array_unique($attributeCodes)));
        $this->attachMagentoOptionLookups($mappings, array_values(array_unique($magentoOptionAttributeCodes)));

        return $this->mappings = $mappings;
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param string[]                         $attributeCodes
     */
    private function attachOptionLabels(array &$mappings, array $attributeCodes): void
    {
        if (!$attributeCodes) {
            return;
        }

        $labelsByAttribute = [];
        $rows = $this->getConnection()->fetchAll(
            $this->getConnection()
                ->select()
                ->from(
                    $this->resourceConnection->getTableName('ergonode_attribute_option'),
                    ['attribute_code', 'option_code', 'labels_json']
                )
                ->where('attribute_code IN (?)', $attributeCodes)
        );

        foreach ($rows as $row) {
            $attributeCode = (string)$row['attribute_code'];
            $optionCode = (string)$row['option_code'];
            $labelsByAttribute[$attributeCode][$optionCode] = $this->decodeLabels((string)$row['labels_json']);
        }

        foreach ($mappings as &$mapping) {
            $mapping['option_labels'] = $labelsByAttribute[$mapping['ergonode_attribute_code']] ?? [];
        }
        unset($mapping);
    }

    /**
     * @param array<int, array<string, mixed>> $mappings
     * @param string[]                         $attributeCodes
     */
    private function attachMagentoOptionLookups(array &$mappings, array $attributeCodes): void
    {
        $attributeCodes = array_values(
            array_filter(
                array_map(
                    static fn (string $code): string => trim($code),
                    $attributeCodes
                )
            )
        );

        if (!$attributeCodes) {
            return;
        }

        $connection = $this->getConnection();
        $rows = $connection->fetchAll(
            $connection
                ->select()
                ->from(['attribute' => $this->resourceConnection->getTableName('eav_attribute')], ['attribute_code'])
                ->join(
                    ['entity_type' => $this->resourceConnection->getTableName('eav_entity_type')],
                    'entity_type.entity_type_id = attribute.entity_type_id',
                    []
                )
                ->join(
                    ['option' => $this->resourceConnection->getTableName('eav_attribute_option')],
                    'option.attribute_id = attribute.attribute_id',
                    ['option_id']
                )
                ->join(
                    ['value' => $this->resourceConnection->getTableName('eav_attribute_option_value')],
                    'value.option_id = option.option_id',
                    ['label' => 'value']
                )
                ->where('entity_type.entity_type_code = ?', 'catalog_product')
                ->where('attribute.attribute_code IN (?)', $attributeCodes)
                ->where('value.value <> ?', '')
                ->order('value.store_id ASC')
                ->order('option.sort_order ASC')
                ->order('option.option_id ASC')
        );

        $lookups = [];
        foreach ($rows as $row) {
            $attributeCode = (string)$row['attribute_code'];
            $optionId = (int)$row['option_id'];
            $labelKey = $this->normalizeOptionLookupLabel((string)$row['label']);

            if ($attributeCode === '' || $optionId <= 0 || $labelKey === '') {
                continue;
            }

            $lookups[$attributeCode][$labelKey] ??= $optionId;
            $lookups[$attributeCode][$this->normalizeOptionLookupLabel((string)$optionId)] ??= $optionId;
            $lookups[$attributeCode][$this->normalizeOptionLookupLabel('option_' . $optionId)] ??= $optionId;
        }

        foreach ($mappings as &$mapping) {
            $attributeCode = (string)($mapping['magento_attribute_code'] ?? '');
            $mapping['magento_option_ids_by_label'] = $lookups[$attributeCode] ?? [];
        }
        unset($mapping);
    }

    /**
     * @return array<string, string>
     */
    private function decodeLabels(string $labelsJson): array
    {
        try {
            $labels = $this->json->unserialize($labelsJson);
        } catch (InvalidArgumentException) {
            return [];
        }

        if (!is_array($labels)) {
            return [];
        }

        $result = [];
        foreach ($labels as $language => $label) {
            $language = (string)$language;
            $label = (string)$label;
            if ($language !== '' && $label !== '') {
                $result[$language] = $label;
            }
        }

        return $result;
    }

    private function normalizeOptionLookupLabel(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (is_string($ascii) && $ascii !== '') {
            $label = $ascii;
        }

        return preg_replace('/[^a-z0-9]+/', '', $label) ?? '';
    }

    public function reset(): void
    {
        $this->mappings = null;
    }

    private function getConnection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
