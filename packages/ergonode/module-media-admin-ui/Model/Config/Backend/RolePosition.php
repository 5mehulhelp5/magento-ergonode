<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class RolePosition extends Value
{
    public function beforeSave(): self
    {
        $value = (string)$this->getValue();
        if ($this->getData('scope') !== 'default' || !ctype_digit($value) || (int)$value < 2 || (int)$value > 65535) {
            throw new LocalizedException(__('Choose a global image role position between 2 and 65535.'));
        }
        $this->setValue((int)$value);
        return parent::beforeSave();
    }
}
