<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Source;

use Ergonode\ProductMedia\Api\AdditionalRoleOptionsInterface;
use Magento\Framework\Data\OptionSourceInterface;

class AdditionalRole implements OptionSourceInterface
{
    public function __construct(private readonly AdditionalRoleOptionsInterface $roles)
    {
    }
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => (string)__('None')]];
        foreach ($this->roles->getOptions() as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }
}
