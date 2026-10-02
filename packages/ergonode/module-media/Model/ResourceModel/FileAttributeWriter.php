<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\ResourceModel;

use Ergonode\Media\Model\Port\FileAttributeWriterInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Action;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class FileAttributeWriter implements FileAttributeWriterInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductResource $products,
        private readonly Config $eav,
        private readonly Action $action,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function write(int $productId, string $code, int $storeId, string $path): void
    {
        $attribute = $this->eav->getAttribute(Product::ENTITY, $code);
        $value = in_array($attribute->getFrontendInput(), ['text', 'textarea'], true)
            ? rtrim($this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/')
                . '/' . ltrim($path, '/')
            : $path;
        $this->action->updateAttributes([$productId], [$code => $value], $storeId);
    }
    public function clear(int $productId, string $code, int $storeId): void
    {
        $attribute = $this->eav->getAttribute(Product::ENTITY, $code);
        $id = (int)$attribute->getAttributeId();
        $table = (string)$attribute->getBackendTable();
        if ($id < 1 || $table === '' || $attribute->getBackendType() === 'static') {
            return;
        }
        $link = $this->products->getLinkField();
        $value = $productId;
        if ($link !== $this->products->getIdFieldName()) {
            $select = $this->resource->getConnection()->select()
                ->from($this->products->getEntityTable(), [$link])
                ->where($this->products->getIdFieldName() . ' = ?', $productId)
                ->limit(1);
            $value = (int)$this->resource->getConnection()->fetchOne($select);
        }
        $scope = (int)$attribute->getIsGlobal();
        $storeIds = $scope === 1 ? [0] : [$storeId];
        if ($scope === 2 && $storeId !== 0) {
            $storeIds = $this->storeManager->getStore($storeId)->getWebsite()->getStoreIds(true);
        }
        $this->resource->getConnection()->delete(
            $this->resource->getTableName($table),
            [
                'attribute_id = ?' => $id,
                $link . ' = ?' => $value,
                'store_id IN (?)' => $storeIds,
            ]
        );
    }
}
