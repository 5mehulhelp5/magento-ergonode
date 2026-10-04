<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Model\Gallery;

use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Model\Port\RoleWriterInterface;
use Ergonode\ProductMedia\Model\ResourceModel\NativeGalleryPaths;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Store\Model\StoreManagerInterface;

/** Clear references to images removed or hidden by the completed gallery write. */
class ObsoleteImageRoles
{
    public function __construct(
        private readonly Product $products,
        private readonly ImageRolesInterface $available,
        private readonly RoleWriterInterface $writer,
        private readonly StoreManagerInterface $stores,
        private readonly NativeGalleryPaths $galleryPaths
    ) {
    }

    /** @return list<string> Paths removed from roles, including old partial states. */
    public function clearMissing(int $productId): array
    {
        $codes = array_keys($this->available->getOptions());
        if ($codes === []) {
            return [];
        }
        $retired = [];
        $storeIds = [0 => 0];
        foreach ($this->stores->getStores() as $store) {
            $storeIds[(int)$store->getId()] = (int)$store->getId();
        }
        foreach ($storeIds as $storeId) {
            $nativePaths = $this->galleryPaths->get($productId, $storeId);
            $current = $this->products->getAttributeRawValue($productId, $codes, $storeId);
            $current = is_array($current) ? $current : [$codes[0] => $current];
            $clear = [];
            foreach ($current as $code => $value) {
                if (is_string($value) && $value !== '' && $value !== 'no_selection'
                    && !in_array($value, $nativePaths, true)
                ) {
                    $clear[$code] = null;
                    $path = 'catalog/product/' . ltrim($value, '/');
                    $retired[$path] = $path;
                }
            }
            if ($clear !== []) {
                $this->writer->write($productId, $storeId, $clear);
            }
        }
        return array_values($retired);
    }
}
