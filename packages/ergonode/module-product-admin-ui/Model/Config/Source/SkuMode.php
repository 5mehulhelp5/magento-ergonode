<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Model\Config\Source;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Magento\Framework\Data\OptionSourceInterface;

class SkuMode implements OptionSourceInterface
{
    public function __construct(private readonly ProductIdentityModeProviderInterface $identityModeProvider)
    {
    }

    public function toOptionArray(): array
    {
        $options = [[
            'value' => '',
            'label' => __('Choose SKU mode'),
        ]];
        $options[] = [
            'value' => ProductIdentityModeProviderInterface::MODE_MAPPED,
            'label' => __('Use a Magento attribute as Ergonode SKU'),
        ];
        if ($this->identityModeProvider->isAssignedModeAvailable()) {
            $options[] = [
                'value' => ProductIdentityModeProviderInterface::MODE_ASSIGNED,
                'label' => __('Let Ergonode assign SKU; publish Magento SKU as a separate attribute'),
            ];
        } elseif ($this->identityModeProvider->getMode() === ProductIdentityModeProviderInterface::MODE_ASSIGNED) {
            $options[] = [
                'value' => ProductIdentityModeProviderInterface::MODE_ASSIGNED,
                'label' => __('Independent SKUs unavailable — restore product attribute mapping support'),
            ];
        }

        return $options;
    }
}
