<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;

class MappingStateBuilder
{
    public function __construct(
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly ProductAttributePolicy $attributePolicy,
        private readonly ValueAdapterRegistry $valueAdapters
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>    $rows
     * @param  array<string, array<string, mixed>> $ergonodeAttributes
     * @param  array<string, array<string, mixed>> $magentoAttributes
     * @return array<int, array<string, mixed>>
     */
    public function attributes(array $rows, array $ergonodeAttributes, array $magentoAttributes): array
    {
        $result = [];
        foreach ($rows as $row) {
            $leftCode = trim((string)($row['ergonode_attribute_code'] ?? ''));
            $rightCode = trim((string)($row['magento_attribute_code'] ?? ''));
            if (($leftCode !== '' && !$this->attributePolicy->isErgonodeMappable($leftCode))
                || ($rightCode !== '' && !$this->attributePolicy->isMappable($rightCode))
            ) {
                continue;
            }
            $left = $this->side($leftCode, $ergonodeAttributes);
            $right = $this->side($rightCode, $magentoAttributes);
            $result[] = [
                'mapping_id' => (int)$row['mapping_id'], 'left' => $left, 'right' => $right,
                'value_adapter' => $row['value_adapter'] ?? $this->valueAdapters->getRequiredCode($rightCode),
                'adapter_availability' => [
                    'import' => $this->valueAdapters->isAvailable($row['value_adapter'] ?? null, $rightCode, 'import'),
                    'publish' => $this->valueAdapters->isAvailable(
                        $row['value_adapter'] ?? null,
                        $rightCode,
                        'publish'
                    ),
                ],
                'tone' => !$left || !$right ? 'warning' : (
                    $this->typeCompatibility->canMapAttributes($left['type'], $right['type']) ? 'ok' : 'error'
                ),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @return array<int, array<string, mixed>>
     */
    public function optionContexts(array $mappings): array
    {
        $contexts = [];
        foreach ($mappings as $mapping) {
            if (!$mapping['left'] || !$mapping['right']
                || !$this->typeCompatibility->canMapOptions($mapping['left']['type'], $mapping['right']['type'])
            ) {
                continue;
            }
            $contexts[] = [
                'code' => (string)$mapping['mapping_id'], 'mapping_id' => (int)$mapping['mapping_id'],
                'left' => $mapping['left'], 'right' => $mapping['right'],
            ];
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>      $row
     * @param  array<string, mixed>|null $ergonodeAttribute
     * @param  array<string, mixed>|null $magentoAttribute
     * @return array<string, mixed>|null
     */
    public function context(array $row, ?array $ergonodeAttribute, ?array $magentoAttribute): ?array
    {
        if (!$this->attributePolicy->isErgonodeMappable((string)($row['ergonode_attribute_code'] ?? ''))
            || $this->attributePolicy->isIdentityAttribute((string)($row['magento_attribute_code'] ?? ''))
        ) {
            return null;
        }

        return [
            'mapping_id' => (int)$row['mapping_id'],
            'ergonode_attribute_code' => (string)($row['ergonode_attribute_code'] ?? ''),
            'magento_attribute_code' => (string)($row['magento_attribute_code'] ?? ''),
            'ergonode_type' => (string)($ergonodeAttribute['type'] ?? ''),
            'magento_type' => (string)($magentoAttribute['type'] ?? ''),
            'magento_has_custom_source' => !empty($magentoAttribute['has_custom_source']),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @param  array<int, array<string, mixed>> $ergonodeOptions
     * @param  array<int, array<string, mixed>> $magentoOptions
     * @return array<int, array<string, mixed>>
     */
    public function options(array $rows, array $ergonodeOptions, array $magentoOptions): array
    {
        $leftOptions = array_column($ergonodeOptions, null, 'code');
        $rightOptions = array_column($magentoOptions, null, 'code');
        $result = [];
        foreach ($rows as $row) {
            $left = $this->side((string)($row['ergonode_option_code'] ?? ''), $leftOptions, 'option');
            $rightCode = isset($row['magento_option_id']) ? 'option_' . (string)$row['magento_option_id'] : '';
            $right = $this->side($rightCode, $rightOptions, 'option');
            $result[] = [
                'left' => $left, 'right' => $right,
                'tone' => ($row['status'] ?? 'complete') === 'error' ? 'error' : ($left && $right ? 'ok' : 'warning'),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private function side(string $code, array $items, string $missingType = 'missing'): ?array
    {
        if ($code === '') {
            return null;
        }

        return $items[$code] ?? [
            'label' => $code, 'code' => $code, 'type' => $missingType, 'scope' => 'missing',
            'has_custom_source' => false,
        ];
    }
}
