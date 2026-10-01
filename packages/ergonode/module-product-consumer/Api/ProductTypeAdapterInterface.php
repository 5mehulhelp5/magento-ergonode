<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

interface ProductTypeAdapterInterface
{
    /** @return string */
    public function getMagentoType(): string;

    /** @return string */
    public function getErgonodeType(): string;

    /** @return bool */
    public function isDefaultForErgonodeType(): bool;

    /**
     * Reconcile type-specific product relationships.
     *
     * @param int $productId
     * @param string $sku
     * @param RemoteProduct $source
     * @return void
     */
    public function synchronizeRelations(int $productId, string $sku, RemoteProduct $source): void;
}
