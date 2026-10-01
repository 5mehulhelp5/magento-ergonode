<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Api;

interface CategoryAttributeMappingProviderInterface
{
    /**
     * @return array<int, array{
     *     mapping_id: int,
     *     ergonode_attribute_code: string,
     *     magento_attribute_code: string,
     *     ergonode_type: string,
     *     magento_type: string,
     *     option_ids: array<string, int>,
     *     option_labels: array<string, array<string, string>>,
     *     magento_option_ids_by_label: array<string, int>
     * }>
     */
    public function getValueMappings(): array;
}
