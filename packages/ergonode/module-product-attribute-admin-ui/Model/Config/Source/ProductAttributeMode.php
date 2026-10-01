<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Config\Source;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributeMode implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => ProductAttributePolicy::MODE_MAPPING, 'label' => __('Map from an Ergonode attribute')],
            [
                'value' => ProductAttributePolicy::MODE_MANUAL,
                'label' => __('Managed by Magento — use a default when creating'),
            ],
        ];
    }
}
