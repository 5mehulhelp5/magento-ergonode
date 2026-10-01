<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Api;

interface ProductAttributeSourcePreparationInterface
{
    /**
     * Reconcile source definitions before loading and writing a product's mapped values.
     *
     * @return void
     */
    public function prepare(): void;
}
