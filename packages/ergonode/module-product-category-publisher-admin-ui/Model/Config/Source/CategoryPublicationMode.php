<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryPublisherAdminUi\Model\Config\Source;

use Ergonode\ProductCategoryPublisher\Model\Config\CategoryPublicationConfig;
use Magento\Framework\Data\OptionSourceInterface;

class CategoryPublicationMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => CategoryPublicationConfig::MODE_KEEP,
                'label' => __('Keep existing'),
            ],
            [
                'value' => CategoryPublicationConfig::MODE_MATCH,
                'label' => __('Match Magento'),
            ],
        ];
    }
}
