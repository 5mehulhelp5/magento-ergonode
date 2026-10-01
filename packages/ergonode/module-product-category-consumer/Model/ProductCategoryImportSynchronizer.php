<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryConsumer\Model;

use Ergonode\ProductCategoryConsumer\Model\GraphQl\RemoteProductCategoryCodeLoader;
use Ergonode\ProductConsumer\Api\ProductImportHashProviderInterface;
use Ergonode\ProductConsumer\Api\ProductStateSynchronizerInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Catalog\Api\CategoryLinkManagementInterface;

class ProductCategoryImportSynchronizer implements ProductImportHashProviderInterface, ProductStateSynchronizerInterface
{
    public function __construct(
        private readonly RemoteProductCategoryCodeLoader $categoryCodeLoader,
        private readonly CategoryIdsResolver $categoryIdsResolver,
        private readonly CategoryLinkManagementInterface $categoryLinkManagement
    ) {
    }

    public function getHash(RemoteProduct $source): string
    {
        return hash('sha256', (string)json_encode(
            $this->categoryCodeLoader->load($source->sku),
            JSON_THROW_ON_ERROR
        ));
    }

    public function synchronize(int $productId, string $magentoSku, RemoteProduct $source): void
    {
        unset($productId);
        $categoryIds = $this->categoryIdsResolver->resolve($this->categoryCodeLoader->load($source->sku));
        $this->categoryLinkManagement->assignProductToCategories($magentoSku, $categoryIds);
    }
}
