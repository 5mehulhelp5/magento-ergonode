<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface CompleteMappingProviderInterface
{
    /**
     * Read complete mappings and their option IDs without importing remote snapshots.
     *
     * @param string|null $direction Import or publish filters unavailable adapters; null reads all mappings.
     * @return array<int, array{
     *     mapping_id: int,
     *     ergonode_attribute_code: string,
     *     magento_attribute_code: string,
     *     ergonode_type: string,
     *     magento_type: string,
     *     value_adapter: string|null,
     *     option_ids: array<string, int>
     * }>
     */
    public function getMappings(?string $direction = null): array;
}
