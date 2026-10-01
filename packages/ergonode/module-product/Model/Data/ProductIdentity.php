<?php

declare(strict_types=1);

namespace Ergonode\Product\Model\Data;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use InvalidArgumentException;

final readonly class ProductIdentity implements ProductIdentityInterface
{
    public function __construct(
        private int $productId,
        private string $magentoSku,
        private string $ergonodeSku,
        private string $identityMode
    ) {
        if ($this->productId < 1
            || trim($this->magentoSku) === ''
            || trim($this->ergonodeSku) === ''
            || !in_array($this->identityMode, [self::MODE_SHARED, self::MODE_ASSIGNED, self::MODE_MAPPED], true)
        ) {
            throw new InvalidArgumentException('Invalid Ergonode product identity.');
        }
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getMagentoSku(): string
    {
        return $this->magentoSku;
    }

    public function getErgonodeSku(): string
    {
        return $this->ergonodeSku;
    }

    public function getIdentityMode(): string
    {
        return $this->identityMode;
    }
}
