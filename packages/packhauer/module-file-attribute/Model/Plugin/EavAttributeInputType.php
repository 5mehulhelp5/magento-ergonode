<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Model\Plugin;

use Magento\Eav\Model\Entity\Attribute;

class EavAttributeInputType
{
    public function afterGetBackendTypeByInput(Attribute $subject, mixed $result, mixed $type): mixed
    {
        return $type === 'file' ? 'varchar' : $result;
    }

    public function afterGetDefaultValueByInput(Attribute $subject, mixed $result, mixed $type): mixed
    {
        return $type === 'file' ? 'default_value_text' : $result;
    }
}
