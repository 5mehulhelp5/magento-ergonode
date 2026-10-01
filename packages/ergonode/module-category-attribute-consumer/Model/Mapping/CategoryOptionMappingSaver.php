<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\CategoryAttribute\Api\OptionMappingWriterInterface;
use Ergonode\CategoryAttributeConsumer\Model\Provider\MagentoCategoryOptionCreator;
use Magento\Framework\Exception\LocalizedException;

class CategoryOptionMappingSaver
{
    public function __construct(
        private readonly CategoryAttributeMappingProvider $attributeMappingProvider,
        private readonly MagentoCategoryOptionCreator $optionCreator,
        private readonly OptionMappingWriterInterface $mappingWriter
    ) {
    }

    /**
     * @param int $attributeMappingId
     * @param array<int, array<string, mixed>> $mappings
     * @param array<int, array<string, mixed>> $visibility
     * @return array{inserted: int, updated: int, deleted: int, unchanged: int, options_created: int}
     */
    public function save(int $attributeMappingId, array $mappings, array $visibility): array
    {
        $attribute = $this->attributeMappingProvider->getMappingRow($attributeMappingId);
        if (!$attribute || (string)($attribute['status'] ?? '') !== 'complete') {
            throw new LocalizedException(__('Options require a saved category attribute mapping.'));
        }
        $created = 0;
        foreach ($mappings as &$mapping) {
            $left = is_array($mapping['left'] ?? null) ? $mapping['left'] : null;
            $right = is_array($mapping['right'] ?? null) ? $mapping['right'] : null;
            if ($right && (!empty($right['pending_create'])
                || str_starts_with((string)($right['code'] ?? ''), 'pending_'))
            ) {
                $label = trim((string)($right['create_label'] ?? $left['label'] ?? ''));
                $mapping['right'] = $this->optionCreator->create((string)$attribute['magento_attribute_code'], $label);
                $created += !empty($mapping['right']['created']) ? 1 : 0;
            }
        }
        unset($mapping);
        $stats = $this->mappingWriter->save($attributeMappingId, $mappings, $visibility);
        $stats['options_created'] = $created;

        return $stats;
    }
}
