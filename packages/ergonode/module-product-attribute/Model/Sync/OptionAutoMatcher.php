<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Sync;

use Ergonode\Attribute\Api\AttributeTypeCompatibilityInterface;
use Ergonode\ProductAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\ProductAttribute\Api\MappingReaderInterface;
use Magento\Framework\Exception\LocalizedException;

class OptionAutoMatcher implements OptionAutoMatcherInterface
{
    public function __construct(
        private readonly MappingReaderInterface $mappingReader,
        private readonly AttributeTypeCompatibilityInterface $typeCompatibility,
        private readonly OptionPairPlanner $planner
    ) {
    }

    public function suggest(
        int $attributeMappingId,
        array $ergonodeOptions,
        array $magentoOptions,
        array $storeLanguages = []
    ): array {
        $attributeMapping = $this->mappingReader->getAttributeRow($attributeMappingId);
        if ($attributeMapping === null) {
            throw new LocalizedException(__('Attribute mapping "%1" does not exist.', $attributeMappingId));
        }
        if (!$this->typeCompatibility->canMapOptions(
            (string)($attributeMapping['ergonode_type'] ?? ''),
            (string)($attributeMapping['magento_type'] ?? '')
        )
        ) {
            throw new LocalizedException(__('Options can be matched only for option-mappable attribute pairs.'));
        }

        $savedPairs = [];
        foreach ($this->mappingReader->getOptionRows($attributeMappingId) as $row) {
            $code = trim((string)($row['ergonode_option_code'] ?? ''));
            $optionId = (int)($row['magento_option_id'] ?? 0);
            $hasOptionId = isset($row['magento_option_id'])
                && ($optionId > 0 || ($optionId === 0 && ($attributeMapping['magento_type'] ?? '') === 'boolean'));
            if ($code !== '' && $hasOptionId && ($row['status'] ?? 'complete') === 'complete') {
                $savedPairs[$code] = $optionId;
            }
        }

        return $this->planner->plan($attributeMapping, $ergonodeOptions, $magentoOptions, $storeLanguages, $savedPairs);
    }
}
