<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Model\Config\Source;

use Ergonode\Product\Api\MagentoIdentityAttributeInterface;
use Magento\Framework\Data\OptionSourceInterface;

class MagentoIdentityAttribute implements OptionSourceInterface
{
    public function __construct(private readonly MagentoIdentityAttributeInterface $identityAttribute)
    {
    }

    public function toOptionArray(): array
    {
        $options = [[
            'value' => '',
            'label' => __('Choose Magento product attribute'),
        ]];
        foreach ($this->identityAttribute->getEligibleAttributes() as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => $label . ' (' . $code . ')',
            ];
        }

        return $options;
    }
}
