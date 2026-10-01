<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Api;

interface ProductAttributeMappingProviderInterface
{
    /**
     * Return complete, visible Magento-to-Ergonode attribute mappings.
     *
     * @return array<int, array{
     *     mapping_id: int,
     *     ergonode_attribute_code: string,
     *     magento_attribute_code: string,
     *     ergonode_type: string,
     *     magento_type: string,
     *     option_ids: array<string, int>,
     *     option_labels: array<string, array<string, string>>,
     *     magento_option_ids_by_label: array<string, int>,
     *     magento_has_custom_source: bool,
     *     magento_source_option_values: array<int, int|string>
     * }>
     */
    public function getMappings(): array;
}
