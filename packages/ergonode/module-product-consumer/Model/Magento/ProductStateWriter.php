<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Magento;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Ergonode\ProductConsumer\Api\ProductTypeAdapterInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

class ProductStateWriter
{
    public function __construct(
        private readonly ProductAttributeValueMapper $valueMapper,
        private readonly ProductAttributeWriter $attributeWriter,
        private readonly ProductStateSynchronizerPool $stateSynchronizers,
        private readonly ProductUrlKeyWriter $urlKeyWriter,
        private readonly MagentoSkuSynchronizer $magentoSkuSynchronizer
    ) {
    }

    /**
     * @param array{
     *     values: array<string, array<int, float|int|string|string[]>>,
     *     clear: array<string, int[]>
     * }|null $resolvedSpecial
     */
    public function write(
        int $productId,
        RemoteProduct $source,
        ProductTypeAdapterInterface $adapter,
        ?string $magentoSku = null,
        ?array $resolvedSpecial = null,
        string $identityMode = ProductIdentityInterface::MODE_SHARED
    ): void {
        $magentoSku = trim($magentoSku ?? $source->sku);
        $mapped = $this->valueMapper->map($source->attributes, null, $resolvedSpecial);
        $this->magentoSkuSynchronizer->assertIdentityAttributeNotMapped($mapped, $identityMode);
        $urlKeyValues = $mapped['values']['url_key'] ?? [];
        $urlKeyClear = $mapped['clear']['url_key'] ?? [];
        unset($mapped['values']['url_key'], $mapped['clear']['url_key']);
        $this->attributeWriter->write($productId, $mapped['values'], $mapped['clear']);
        $this->stateSynchronizers->synchronize($productId, $magentoSku, $source);
        $this->urlKeyWriter->write($productId, $urlKeyValues, $urlKeyClear);
        $adapter->synchronizeRelations($productId, $magentoSku, $source);
    }
}
