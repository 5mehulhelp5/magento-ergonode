<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Source;

use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => ConfigProvider::ENVIRONMENT_TEST,
                'label' => __('Test'),
            ],
            [
                'value' => ConfigProvider::ENVIRONMENT_PRODUCTION,
                'label' => __('Production'),
            ],
        ];
    }
}
