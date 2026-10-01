<?php

declare(strict_types=1);

namespace PackHauer\UnitAttribute\Model\Plugin;

use Magento\Catalog\Helper\Product;
use PackHauer\UnitAttribute\Model\Attribute\Backend\Unit;

class ProductAttributeInputType
{
    /**
     * @param array<string, array<string, string>> $result
     * @return array<string, array<string, string>>
     */
    public function afterGetAttributeInputTypes(Product $subject, array $result): array
    {
        $result['unit'] = ['backend_model' => Unit::class];

        return $result;
    }
}
