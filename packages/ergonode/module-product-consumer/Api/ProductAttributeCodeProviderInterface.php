<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Api;

interface ProductAttributeCodeProviderInterface
{
    /**
     * Return additional Ergonode attribute codes required by an optional product import extension.
     *
     * @return string[]
     */
    public function getAttributeCodes(): array;
}
