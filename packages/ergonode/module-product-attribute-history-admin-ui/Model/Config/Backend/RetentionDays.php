<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistoryAdminUi\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;

class RetentionDays extends Value
{
    public function beforeSave(): AbstractModel
    {
        $days = filter_var($this->getValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($days === false) {
            throw new LocalizedException(__('History retention must be a positive whole number of days.'));
        }
        $this->setValue($days);

        return parent::beforeSave();
    }
}
