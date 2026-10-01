<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class InitialPageSize extends Value
{
    public function beforeSave(): AbstractModel
    {
        $value = filter_var($this->getValue(), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 100, 'max_range' => 1000],
        ]);
        if ($value === false) {
            throw new LocalizedException(__('Limit/req must be an integer from 100 to 1000.'));
        }
        $this->setValue($value);

        return parent::beforeSave();
    }
}
