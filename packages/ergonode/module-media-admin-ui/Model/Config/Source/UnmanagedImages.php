<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Source;

use Ergonode\ProductMedia\Api\UnmanagedImagesMode;
use Magento\Framework\Data\OptionSourceInterface;

class UnmanagedImages implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => UnmanagedImagesMode::Keep->value, 'label' => __('Disabled - keep additional Magento images')],
            ['value' => UnmanagedImagesMode::Hide->value, 'label' => __('Hide additional Magento images')],
            ['value' => UnmanagedImagesMode::Remove->value, 'label' => __('Synchronize one to one - unlink additional Magento images')],
        ];
    }
}
