<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\AttributeConsumer\Api\ErgonodeOptionProviderInterface;
use Ergonode\CategoryAttribute\Api\MagentoAttributeProviderInterface;
use Ergonode\CategoryAttribute\Api\MappingReaderInterface;
use Ergonode\CategoryAttribute\Api\MappingStateBuilderInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Ergonode\CategoryAttributeConsumer\Model\Provider\ErgonodeCategoryAttributeProvider;

class CategoryAttributeMappingProvider implements CategoryAttributeMappingProviderInterface
{
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly MappingStateBuilderInterface $stateBuilder,
        private readonly ErgonodeOptionProviderInterface $optionProvider,
        private readonly ErgonodeCategoryAttributeProvider $ergonodeAttributeProvider,
        private readonly MagentoAttributeProviderInterface $magentoAttributeProvider,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly CategoryAttributePolicy $attributePolicy,
        private readonly CategoryAttributeValueMappingFactory $valueMappingFactory
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function getMappings(): array
    {
        $rows = array_values(array_filter(
            $this->mappingReader->getAttributeRows(),
            fn (array $row): bool => empty($row['magento_attribute_code'])
                || $this->attributePolicy->isMappable((string)$row['magento_attribute_code'])
        ));

        return $this->stateBuilder->attributes(
            $rows,
            $this->ergonodeAttributeProvider->getAttributeMap(),
            $this->magentoAttributeProvider->getAttributeMap()
        );
    }

    public function getValueMappings(): array
    {
        $rows = array_filter(
            $this->mappingReader->getAttributeRows(),
            static fn (array $row): bool => $row['ergonode_attribute_code'] !== null
                && $row['magento_attribute_code'] !== null && $row['status'] === 'complete'
        );
        $eligible = [];
        $optionCodes = [];
        foreach ($rows as $row) {
            $code = (string)$row['ergonode_attribute_code'];
            if (!$this->ergonodeAttributeProvider->isAvailableForSynchronization($code)
                || !$this->attributePolicy->isMappable((string)$row['magento_attribute_code'])
            ) {
                continue;
            }
            $eligible[] = $row;
            if (in_array((string)$row['ergonode_type'], ['select', 'multiselect', 'multi_select'], true)) {
                $optionCodes[] = (string)$row['ergonode_attribute_code'];
            }
        }
        $mappings = [];
        $mappingIds = [];
        $mappingIdsByCode = [];
        foreach ($eligible as $row) {
            $mappingId = (int)$row['mapping_id'];
            $mappings[$mappingId] = $this->valueMappingFactory->create($row);
            $mappingIds[] = $mappingId;
            $mappingIdsByCode[(string)$row['ergonode_attribute_code']][] = $mappingId;
        }

        foreach ($this->optionProvider->iterateOptionDefinitions($optionCodes) as $option) {
            foreach ($mappingIdsByCode[$option['attribute_code']] ?? [] as $mappingId) {
                $mappings[$mappingId]['option_labels'][$option['code']] = $option['labels'];
            }
        }

        if ($mappingIds !== []) {
            foreach ($this->mappingReader->getCompleteOptionRows($mappingIds) as $row) {
                $mappingId = (int)$row['attribute_mapping_id'];
                if (isset($mappings[$mappingId])) {
                    $mappings[$mappingId]['option_ids'][(string)$row['ergonode_option_code']]
                        = (int)$row['magento_option_id'];
                }
            }
        }

        return array_values($mappings);
    }

    /** @return array<int, array<string, mixed>> */
    public function getOptionAttributeContexts(): array
    {
        $contexts = [];
        foreach ($this->getMappings() as $mapping) {
            $left = is_array($mapping['left'] ?? null) ? $mapping['left'] : null;
            $right = is_array($mapping['right'] ?? null) ? $mapping['right'] : null;
            if (!$left || !$right || !$this->typeCompatibility->canMapOptions(
                (string)($left['type'] ?? ''),
                (string)($right['type'] ?? '')
            )) {
                continue;
            }
            $contexts[] = [
                'code' => (string)$mapping['mapping_id'],
                'mapping_id' => (int)$mapping['mapping_id'],
                'left' => $left,
                'right' => $right,
            ];
        }

        return $contexts;
    }

    /** @return array<string, mixed>|null */
    public function getMappingRow(int $mappingId): ?array
    {
        $row = $this->mappingReader->getAttributeRow($mappingId);

        if (!is_array($row)) {
            return null;
        }

        return $this->attributePolicy->isMappable((string)($row['magento_attribute_code'] ?? ''))
            ? $row
            : null;
    }

    /** @return array<int, array{mapped: int, total: int}> */
    public function getOptionMappingProgress(): array
    {
        $contexts = $this->getOptionAttributeContexts();
        if ($contexts === []) {
            return [];
        }

        $mappingIds = array_map(static fn (array $context): int => (int)$context['mapping_id'], $contexts);
        $attributeCodes = array_map(static fn (array $context): string => (string)$context['left']['code'], $contexts);
        $optionTotals = $this->optionProvider->getOptionCounts($attributeCodes);
        $mappedTotals = $this->mappingReader->getCompleteOptionCounts($mappingIds);
        $progress = [];
        foreach ($contexts as $context) {
            $mappingId = (int)$context['mapping_id'];
            $progress[$mappingId] = [
                'mapped' => (int)($mappedTotals[$mappingId] ?? 0),
                'total' => (int)($optionTotals[(string)$context['left']['code']] ?? 0),
            ];
        }

        return $progress;
    }
}
