<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Backend;

use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class UnmanagedImages extends Value
{
    public function beforeSave(): self
    {
        if ($this->getData('scope') !== 'default') {
            throw new LocalizedException(__('Additional Magento image handling must be configured globally.'));
        }
        $this->setValue(UnmanagedImagesMode::fromConfig($this->getValue())->value);
        return parent::beforeSave();
    }
}
