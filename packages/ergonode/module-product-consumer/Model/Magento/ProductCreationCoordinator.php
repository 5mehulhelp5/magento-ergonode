<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Product\Api\ProductIdentityServiceInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class ProductCreationCoordinator
{
    public function __construct(
        private readonly ProductCreator $productCreator,
        private readonly ProductIdentityServiceInterface $identityService,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /** @param array<string, array<int, float|int|string|string[]>> $initialAttributeValues */
    public function createAndBind(
        RemoteProduct $source,
        string $magentoType,
        int $attributeSetId,
        array $initialAttributeValues,
        string $magentoSku,
        string $identityMode
    ): int {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $product = $this->productCreator->create(
                $source,
                $magentoType,
                $attributeSetId,
                $initialAttributeValues,
                $magentoSku
            );
            $productId = (int)$product->getId();
            $this->identityService->bind($productId, $source->sku, $identityMode);
            $connection->commit();

            return $productId;
        } catch (Throwable $exception) {
            $connection->rollBack();
            if ($exception instanceof LocalizedException) {
                throw $exception;
            }
            throw new LocalizedException(__('Unable to create and bind the Magento product identity.'), $exception);
        }
    }
}
