<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Source;

use Ergonode\Core\Model\Config\ConnectionModePool;
use Magento\Framework\Data\OptionSourceInterface;

class OperatingMode implements OptionSourceInterface
{
    public function __construct(private readonly ConnectionModePool $modePool)
    {
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->modePool->getModes() as $mode) {
            $options[] = ['value' => $mode->getCode(), 'label' => __($mode->getLabel())];
        }

        return $options;
    }
}
