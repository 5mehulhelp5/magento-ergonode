<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Type;

use Ergonode\ProductConsumer\Api\ProductTypeAdapterInterface;
use Ergonode\ProductConsumer\Model\ValueObject\Product\RemoteProduct;

class NoRelationTypeAdapter implements ProductTypeAdapterInterface
{
    public function __construct(
        private readonly string $magentoType,
        private readonly string $ergonodeType,
        private readonly bool $defaultForErgonodeType = false
    ) {
    }

    public function getMagentoType(): string
    {
        return $this->magentoType;
    }

    public function getErgonodeType(): string
    {
        return $this->ergonodeType;
    }

    public function isDefaultForErgonodeType(): bool
    {
        return $this->defaultForErgonodeType;
    }

    public function synchronizeRelations(int $productId, string $sku, RemoteProduct $source): void
    {
    }
}
