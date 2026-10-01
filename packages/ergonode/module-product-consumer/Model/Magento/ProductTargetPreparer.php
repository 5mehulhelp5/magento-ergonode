<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\ProductConsumer\Api\ProductTypeAdapterPoolInterface;
use Ergonode\ProductConsumer\Model\Data\PreparedProduct;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductTargetPreparer
{
    public function __construct(
        private readonly ProductIdentityTargetResolver $identityTargetResolver,
        private readonly ProductTypeDiscriminator $typeDiscriminator,
        private readonly ProductTypeAdapterPoolInterface $typeAdapterPool,
        private readonly TemplateAttributeSetResolver $attributeSetResolver,
        private readonly ProductCreationCoordinator $productCreationCoordinator,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /** @param array<string, array<int, float|int|string|string[]>> $initialAttributeValues */
    public function prepare(RemoteProduct $source, array $initialAttributeValues = []): PreparedProduct
    {
        $resolved = $this->identityTargetResolver->resolve($source);
        $magentoSku = $resolved->magentoSku;
        $target = $resolved->target;
        $adapter = $this->typeAdapterPool->resolve(
            $source->type,
            $target['type_id'] ?? null,
            $this->typeDiscriminator->resolve($source)
        );
        $attributeSetId = $this->attributeSetResolver->resolve($source->templateCode);
        if ($target === null) {
            $productId = $this->productCreationCoordinator->createAndBind(
                $source,
                $adapter->getMagentoType(),
                $attributeSetId,
                $initialAttributeValues,
                $magentoSku,
                $resolved->identityMode
            );

            return new PreparedProduct($productId, $adapter, $magentoSku, $magentoSku, $resolved->identityMode);
        }
        if ($target['attribute_set_id'] !== $attributeSetId) {
            $product = $this->productRepository->getById($target['product_id'], true, 0, true);
            $product->setAttributeSetId($attributeSetId);
            $this->productRepository->save($product);
        }

        return new PreparedProduct(
            $target['product_id'],
            $adapter,
            $magentoSku,
            $target['sku'],
            $resolved->identityMode
        );
    }
}
