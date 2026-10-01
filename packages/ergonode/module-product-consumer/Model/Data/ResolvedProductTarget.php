<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumer\Model\Data;

final readonly class ResolvedProductTarget
{
    /** @param array{
     *     product_id: int,
     *     sku: string,
     *     type_id: string,
     *     attribute_set_id: int,
     *     identity_mode: string
     * }|null $target
     */
    public function __construct(
        public string $identityMode,
        public string $magentoSku,
        public ?array $target
    ) {
    }
}
