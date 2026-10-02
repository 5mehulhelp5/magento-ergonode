<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\ResourceModel;

use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;

class ImageRoles implements ImageRolesInterface
{
    public function __construct(private readonly CollectionFactory $attributes)
    {
    }
    public function getOptions(): array
    {
        $collection = $this->attributes->create();
        $collection->addFieldToFilter('frontend_input', 'media_image');
        $options = [];
        foreach ($collection as $attribute) {
            $code = (string)$attribute->getAttributeCode();
            $options[$code] = (string)$attribute->getFrontendLabel() . ' (' . $code . ')';
        }
        return $options;
    }
}
