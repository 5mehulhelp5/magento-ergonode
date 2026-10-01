<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryCreationConfigurationProviderInterface;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributeConfigProvider;
use Magento\Framework\Exception\LocalizedException;

class CategoryCreationConfigurationProvider implements CategoryCreationConfigurationProviderInterface
{
    public function __construct(
        private readonly CategoryAttributeConfigProvider $configProvider,
        private readonly CategoryAttributePolicy $attributePolicy,
        private readonly CategoryAttributeMappingProviderInterface $attributeMappingProvider
    ) {
    }

    /**
     * @return array{
     *     attributes_enabled: bool,
     *     fixed_values: array<string, int>,
     *     mapped_attribute_codes: string[]
     * }
     * @throws LocalizedException
     */
    public function get(): array
    {
        $attributesEnabled = $this->configProvider->isAttributeSynchronizationEnabled();
        $mappedAttributeCodes = $this->attributePolicy->getMappedCreationAttributeCodes();
        if ($mappedAttributeCodes !== [] && !$attributesEnabled) {
            throw new LocalizedException(__(
                'Enable category attribute synchronization or configure fixed values for Is Active '
                    . 'and Include in Navigation Menu.'
            ));
        }
        if ($mappedAttributeCodes !== []) {
            $completeMappings = [];
            foreach ($this->attributeMappingProvider->getValueMappings() as $mapping) {
                $completeMappings[(string)$mapping['magento_attribute_code']] = true;
            }
            $missingMappings = array_values(array_filter(
                $mappedAttributeCodes,
                static fn (string $attributeCode): bool => !isset($completeMappings[$attributeCode])
            ));
            if ($missingMappings !== []) {
                throw new LocalizedException(__(
                    'Required category creation attributes are not completely mapped: %1. '
                        . 'Complete their Ergonode mappings or select Magento defaults in '
                        . 'Stores > Configuration > Ergonode > Categories > Attributes.',
                    implode(', ', $missingMappings)
                ));
            }
        }

        return [
            'attributes_enabled' => $attributesEnabled,
            'fixed_values' => $this->attributePolicy->getManualCreationValues(),
            'mapped_attribute_codes' => $mappedAttributeCodes,
        ];
    }
}
