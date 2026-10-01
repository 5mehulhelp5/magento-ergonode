<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Model\Config\Source;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Magento\Framework\Data\OptionSourceInterface;

class CategoryAttributeMode implements OptionSourceInterface
{
    /** @return array<int, array{value: string, label: \Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => CategoryAttributePolicy::MODE_MAPPING,
                'label' => __('Map from an Ergonode attribute'),
            ],
            [
                'value' => CategoryAttributePolicy::MODE_MANUAL,
                'label' => __('Managed by Magento — use a default when creating'),
            ],
        ];
    }
}
