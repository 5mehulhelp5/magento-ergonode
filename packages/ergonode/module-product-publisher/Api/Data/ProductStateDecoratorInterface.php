<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api\Data;

interface ProductStateDecoratorInterface extends ProductStateInterface
{
    /** @return ProductStateInterface */
    public function getDecoratedProductState(): ProductStateInterface;
}
