<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;
use Ergonode\CategoryAttribute\Api\MagentoOptionProviderInterface;

class CategoryAttributeValueMappingFactory
{
    public function __construct(
        private readonly ErgonodeAttributeTypeResolverInterface $typeResolver,
        private readonly MagentoOptionProviderInterface $magentoOptionProvider,
        private readonly OptionLabelKeyNormalizer $optionLabelKeyNormalizer
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function create(array $row): array
    {
        $ergonodeCode = (string)$row['ergonode_attribute_code'];
        $magentoCode = (string)$row['magento_attribute_code'];
        $ergonodeType = $this->typeResolver->toConsumerType((string)$row['ergonode_type']);
        $magentoType = $this->typeResolver->toConsumerType((string)$row['magento_type']);

        return [
            'mapping_id' => (int)$row['mapping_id'],
            'ergonode_attribute_code' => $ergonodeCode,
            'magento_attribute_code' => $magentoCode,
            'ergonode_type' => $ergonodeType,
            'magento_type' => $magentoType,
            'option_ids' => [],
            'option_labels' => [],
            'magento_option_ids_by_label' => in_array($magentoType, ['select', 'multiselect', 'boolean'], true)
                ? $this->magentoOptionLookup($magentoCode)
                : [],
        ];
    }

    /** @return array<string, int> */
    private function magentoOptionLookup(string $attributeCode): array
    {
        $result = [];
        foreach ($this->magentoOptionProvider->getOptions($attributeCode) as $option) {
            if (!preg_match('/^option_(\d+)$/', (string)$option['code'], $matches)) {
                continue;
            }
            $key = $this->optionLabelKeyNormalizer->normalize((string)$option['label']);
            if ($key !== '') {
                $result[$key] ??= (int)$matches[1];
            }
        }

        return $result;
    }
}
