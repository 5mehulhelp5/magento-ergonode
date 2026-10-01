<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Model\Config\Source;

use Ergonode\CategoryAttributeConsumer\Model\Config\CategoryAttributePolicy;
use Magento\Framework\Data\OptionSourceInterface;

class CategoryNameMode implements OptionSourceInterface
{
    /** @return array<int, array{value: string, label: \Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => CategoryAttributePolicy::MODE_MAPPING,
                'label' => __('From an attribute'),
            ],
            [
                'value' => CategoryAttributePolicy::MODE_MANUAL,
                'label' => __('Off'),
            ],
        ];
    }
}
