<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Mapping;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

class AttributeMappingNormalizer
{
    public function __construct(
        private readonly Json $json,
        private readonly ProductAttributeMappingCompatibility $typeCompatibility,
        private readonly ProductAttributePolicy $attributePolicy,
        private readonly ValueAdapterRegistry $valueAdapters
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>> $mappings
     * @param  array<string, array<string, mixed>> $existing
     * @return array<string, array<string, mixed>>
     * @throws LocalizedException
     */
    public function normalize(array $mappings, array $existing = []): array
    {
        $storedAdapters = array_column($existing, 'value_adapter', 'magento_attribute_code');
        $result = $this->reservedMappings($existing);
        $seenErgonode = array_fill_keys(array_filter(array_column($result, 'ergonode_attribute_code')), true);
        $seenMagento = array_fill_keys(array_column($result, 'magento_attribute_code'), true);
        $sortOrder = 0;

        foreach ($mappings as $mapping) {
            $left = isset($mapping['left']) && is_array($mapping['left']) ? $mapping['left'] : null;
            $right = isset($mapping['right']) && is_array($mapping['right']) ? $mapping['right'] : null;
            $leftCode = $left ? trim((string)($left['code'] ?? '')) : '';
            $rightCode = $right ? trim((string)($right['code'] ?? '')) : '';
            if ($leftCode === '' && $rightCode === '') {
                continue;
            }
            if (!empty($left['pending_create']) || !empty($right['pending_create'])) {
                throw new LocalizedException(__('Attributes must exist before their mapping can be saved.'));
            }
            if ($leftCode !== '' && !$this->attributePolicy->isErgonodeMappable($leftCode)) {
                throw new LocalizedException(__('Ergonode attribute "%1" is not available for mapping.', $leftCode));
            }
            if ($rightCode !== '' && !$this->attributePolicy->isMappable($rightCode)) {
                throw new LocalizedException(__('Magento attribute "%1" is not available for mapping.', $rightCode));
            }
            $leftAttribute = $left;
            $rightAttribute = $right;

            $leftType = $leftAttribute ? strtolower(trim((string)($leftAttribute['type'] ?? ''))) : '';
            $rightType = $rightAttribute ? strtolower(trim((string)($rightAttribute['type'] ?? ''))) : '';
            if ($leftType !== '' && !$this->attributePolicy->isTypeMappable($leftType)) {
                throw new LocalizedException(__('This Ergonode attribute type is not available for product mapping.'));
            }
            $logicalKey = $this->key($leftCode, $rightCode);
            if (isset($result[$logicalKey])) {
                continue;
            }

            if ($leftCode !== '' && isset($seenErgonode[$leftCode])) {
                throw new LocalizedException(__('Ergonode attribute "%1" is mapped more than once.', $leftCode));
            }
            if ($rightCode !== '' && isset($seenMagento[$rightCode])) {
                throw new LocalizedException(__('Magento attribute "%1" is mapped more than once.', $rightCode));
            }

            $isComplete = $leftCode !== '' && $rightCode !== '';
            if ($isComplete && ($leftType === '' || $rightType === '')) {
                throw new LocalizedException(
                    __(
                        'Attribute "%1" cannot be mapped to "%2" because type metadata is missing.',
                        $leftCode,
                        $rightCode
                    )
                );
            }
            if ($isComplete && !$this->typeCompatibility->canMapAttributes($leftType, $rightType, $rightCode)) {
                throw new LocalizedException(
                    __(
                        'Attribute "%1" cannot be mapped to "%2" because types do not match.',
                        $leftCode,
                        $rightCode
                    )
                );
            }

            if ($leftCode !== '') {
                $seenErgonode[$leftCode] = true;
            }
            if ($rightCode !== '') {
                $seenMagento[$rightCode] = true;
            }

            $payload = [
                'ergonode_attribute_code' => $leftCode ?: null,
                'magento_attribute_code' => $rightCode ?: null,
                'ergonode_type' => $leftType ?: null,
                'magento_type' => $rightType ?: null,
                'status' => $isComplete ? 'complete' : 'draft',
                'value_adapter' => $storedAdapters[$rightCode] ?? $this->valueAdapters->getRequiredCode($rightCode),
            ];
            $payload['content_hash'] = $this->hash($payload);
            $payload['sort_order'] = $sortOrder++;
            $result[$logicalKey] = $payload;
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $existing
     * @return array<string, array<string, mixed>>
     */
    private function reservedMappings(array $existing): array
    {
        return array_filter($existing, fn (array $row): bool => $this->attributePolicy->isIdentityAttribute(
            (string)($row['magento_attribute_code'] ?? '')
        ));
    }

    public function key(string $leftCode, string $rightCode): string
    {
        if ($leftCode !== '' && $rightCode !== '') {
            return 'full:' . $leftCode . '|' . $rightCode;
        }

        return $leftCode !== '' ? 'ergo:' . $leftCode : 'magento:' . $rightCode;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', $this->json->serialize($payload));
    }
}
