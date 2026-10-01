<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface AttributeBatchImporterInterface
{
    /**
     * Import one remote read-scope page into the shared attribute snapshot.
     *
     * Returns the source cursor without persisting the consumer's execution position.
     * Does not create Magento attributes, mappings or options, or reconcile deletions.
     *
     * @param string|null $cursor
     * @param int|null $requestedPageSize
     * @return array{
     *     has_more: bool,
     *     cursor: string|null,
     *     page_size: int,
     *     imported: int,
     *     changed: int,
     *     unchanged: int,
     *     attribute_codes: string[]
     * }
     * @throws LocalizedException
     */
    public function import(?string $cursor = null, ?int $requestedPageSize = null): array;
}
