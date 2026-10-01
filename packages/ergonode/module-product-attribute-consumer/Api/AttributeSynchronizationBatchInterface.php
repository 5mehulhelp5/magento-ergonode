<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumer\Api;

use Magento\Framework\Exception\LocalizedException;

interface AttributeSynchronizationBatchInterface
{
    /**
     * Import one stream page, persist automatic attribute mappings and synchronize mapped options.
     *
     * Infrastructure or incomplete synchronization failures throw. Completed mapping conflicts,
     * skipped options and option collisions are reported for review and do not block cursor advance.
     * Definition reconciliation may be skipped only for subsequent pages of a process invocation
     * whose first page has already reconciled successfully. Standalone calls reconcile by default.
     *
     * @param string|null $cursor
     * @param int|null $pageSize
     * @param bool $refreshDefinitions
     * @return array{
     *     import: array{
     *         has_more: bool,
     *         cursor: string|null,
     *         page_size: int,
     *         imported: int,
     *         changed: int,
     *         unchanged: int,
     *         attribute_codes: string[]
     *     },
     *     mapping: array<string, int>,
     *     options: array{mappings: array<int, array<string, mixed>>, summary: array<string, int>},
     *     completion: array{cursor_advance_allowed: bool, review_required: int}
     * }
     * @throws LocalizedException
     */
    public function executeAutomatic(
        ?string $cursor = null,
        ?int $pageSize = null,
        bool $refreshDefinitions = true
    ): array;
}
