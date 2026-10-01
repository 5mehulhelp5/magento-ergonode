<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Model\Plugin;

use Magento\Catalog\Helper\Product;
use PackHauer\FileAttribute\Model\Attribute\Backend\File;

class ProductAttributeInputType
{
    /**
     * @param array<string, array<string, string>> $result
     * @return array<string, array<string, string>>
     */
    public function afterGetAttributeInputTypes(Product $subject, array $result): array
    {
        $result['file'] = ['backend_model' => File::class];

        return $result;
    }
}
