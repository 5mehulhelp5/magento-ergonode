<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\ResourceModel;

use Ergonode\ProductMedia\Model\Port\RoleWriterInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Action;

class RoleWriter implements RoleWriterInterface
{
    public function __construct(private readonly ProductResource $products, private readonly Action $action)
    {
    }
    public function write(int $productId, int $storeId, array $roles): void
    {
        if ($roles === []) {
            return;
        }
        $current = $this->products->getAttributeRawValue($productId, array_keys($roles), $storeId);
        $current = is_array($current) ? $current : [array_key_first($roles) => $current];
        $changed = [];
        foreach ($roles as $code => $path) {
            $value = $path === null ? 'no_selection' : '/' . substr($path, strlen('catalog/product/'));
            if (($current[$code] ?? null) !== $value) {
                $changed[$code] = $value;
            }
        }
        if ($changed !== []) {
            $this->action->updateAttributes([$productId], $changed, $storeId);
        }
    }
}
