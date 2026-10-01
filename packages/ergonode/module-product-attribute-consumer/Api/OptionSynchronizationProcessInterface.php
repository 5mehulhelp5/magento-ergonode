<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface OptionSynchronizationProcessInterface
{
    /**
     * Refresh, match, create and persist option mappings using the canonical synchronization process.
     *
     * @param int|null $attributeMappingId Null synchronizes every option-mappable attribute pair.
     * @return array{
     *     mappings: array<int, array{
     *         mapping_id: int,
     *         ergonode_attribute_code: string,
     *         magento_attribute_code: string,
     *         stats: array<string, int>
     *     }>,
     *     summary: array<string, int>
     * }
     * @throws LocalizedException
     */
    public function execute(?int $attributeMappingId = null): array;

    /**
     * Synchronize option mappings belonging to the imported attribute batch.
     *
     * Unlike the standalone execution, this method preserves the change report
     * collected by the parent attribute import process.
     *
     * @param string[] $attributeCodes
     * @return array{
     *     mappings: array<int, array{
     *         mapping_id: int,
     *         ergonode_attribute_code: string,
     *         magento_attribute_code: string,
     *         stats: array<string, int>
     *     }>,
     *     summary: array<string, int>
     * }
     * @throws LocalizedException
     */
    public function executeForAttributeCodes(array $attributeCodes): array;
}
