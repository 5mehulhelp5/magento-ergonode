<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class RequestsPerMinute extends Value
{
    public function beforeSave()
    {
        $value = filter_var($this->getValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($value === false) {
            throw new LocalizedException(__('Requests per minute must be a non-negative integer. Use 0 for no limit.'));
        }
        $this->setValue((string)$value);

        return parent::beforeSave();
    }
}
