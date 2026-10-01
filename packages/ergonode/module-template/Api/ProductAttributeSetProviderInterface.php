<?php

declare(strict_types=1);

namespace Ergonode\Template\Api;

interface ProductAttributeSetProviderInterface
{
    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getProductAttributeSets(): array;
}
