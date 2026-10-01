<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Model\Mapping;

use Magento\Framework\Exception\LocalizedException;
use Ergonode\ProductAttributeConsumer\Model\Provider\MagentoOptionCreator;

class OptionMappingMagentoSyncer
{
    public function __construct(
        private readonly MagentoOptionCreator $magentoOptionCreator
    ) {
    }

    /**
     * @param array<string, mixed> $attributeMapping
     * @param array<string, mixed>|null $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function createPendingMagentoOption(array $attributeMapping, ?array $left, array $right): array
    {
        $leftCode = trim((string)($left['code'] ?? ''));
        if ($leftCode === '') {
            throw new LocalizedException(__('Ergonode option is required to create a Magento option.'));
        }

        $label = trim((string)($right['create_label'] ?? $right['label'] ?? $left['label'] ?? $leftCode));
        if ($label === '') {
            throw new LocalizedException(__('Magento option label is required.'));
        }

        $option = $this->magentoOptionCreator->create(
            (string)$attributeMapping['magento_attribute_code'],
            $label
        );

        return $option + [
            'source' => 'magento',
            'type' => 'option',
        ];
    }
}
