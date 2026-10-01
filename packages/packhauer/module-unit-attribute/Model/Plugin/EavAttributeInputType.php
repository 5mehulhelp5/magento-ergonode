<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Model\Plugin;

use Magento\Eav\Model\Entity\Attribute;

class EavAttributeInputType
{
    public function afterGetBackendTypeByInput(Attribute $subject, mixed $result, mixed $type): mixed
    {
        return $type === 'unit' ? 'decimal' : $result;
    }

    public function afterGetDefaultValueByInput(Attribute $subject, mixed $result, mixed $type): mixed
    {
        return $type === 'unit' ? 'default_value_text' : $result;
    }
}
