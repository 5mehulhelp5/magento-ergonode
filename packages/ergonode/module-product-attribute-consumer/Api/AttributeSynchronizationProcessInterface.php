<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface AttributeSynchronizationProcessInterface
{
    /**
     * Import all attribute pages and synchronize Magento attributes, mappings and options.
     *
     * @param int|null $pageSize
     * @param int $maxBatches
     * @return array{
     *     batches: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     has_more: bool,
     *     created_attributes: int,
     *     auto_mapped: int,
     *     mapping_conflicts: int,
     *     option_mappings: int,
     *     options: array<string, int>,
     *     review_required: int
     * }
     * @throws LocalizedException
     */
    public function executeUntilComplete(?int $pageSize = null, int $maxBatches = 100): array;

    /**
     * Reset the persisted attributeStream cursor without running synchronization.
     *
     * @return void
     * @throws LocalizedException
     */
    public function reset(): void;
}
