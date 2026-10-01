<?php

declare(strict_types=1);

namespace Ergonode\Product\Plugin;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Magento\Catalog\Api\Data\ProductExtensionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Model\ProductRepository;

class ProductRepositoryIdentityPlugin
{
    public function __construct(
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly ProductExtensionFactory $extensionFactory
    ) {
    }

    public function afterGet(ProductRepository $subject, ProductInterface $product): ProductInterface
    {
        return $this->hydrate([$product])[0];
    }

    public function afterGetById(ProductRepository $subject, ProductInterface $product): ProductInterface
    {
        return $this->hydrate([$product])[0];
    }

    public function afterGetList(
        ProductRepository $subject,
        ProductSearchResultsInterface $results
    ): ProductSearchResultsInterface {
        $results->setItems($this->hydrate($results->getItems()));

        return $results;
    }

    /** @param ProductInterface[] $products @return ProductInterface[] */
    private function hydrate(array $products): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (ProductInterface $product): int => (int)$product->getId(),
            $products
        )));
        $identities = $this->identityService->getIdentitiesByProductIds($ids);
        foreach ($products as $product) {
            $extensionAttributes = $product->getExtensionAttributes() ?? $this->extensionFactory->create();
            $identity = $identities[(int)$product->getId()] ?? null;
            $extensionAttributes->setErgonodeSku($identity?->getErgonodeSku());
            $product->setExtensionAttributes($extensionAttributes);
        }

        return $products;
    }
}
