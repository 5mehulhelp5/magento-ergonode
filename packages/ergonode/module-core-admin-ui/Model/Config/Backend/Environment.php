<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Model\Config\Backend;

use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class Environment extends Value
{
    public function beforeSave()
    {
        if (!in_array($this->getValue(), [
            ConfigProvider::ENVIRONMENT_TEST,
            ConfigProvider::ENVIRONMENT_PRODUCTION,
        ], true)) {
            throw new LocalizedException(__('Choose a valid Ergonode environment.'));
        }

        return parent::beforeSave();
    }
}
