<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Model\Config\Source;

use Ergonode\Media\Model\Config\MediaConfig;
use Magento\Framework\Data\OptionSourceInterface;

class GalleryMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => MediaConfig::MODE_SHARED, 'label' => __('Shared files')],
            ['value' => MediaConfig::MODE_SEO, 'label' => __('SEO file per product')],
        ];
    }
}
