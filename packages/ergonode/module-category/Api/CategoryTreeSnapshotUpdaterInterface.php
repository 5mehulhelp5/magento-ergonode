<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategoryTreeSnapshotUpdaterInterface
{
    /**
     * Record the complete, successfully published hierarchy in preorder.
     * Existing translations and mappings are preserved. Confirmed categories must
     * come from server-side mutation results, never from the browser. Any remaining
     * details are read with the write credential before the atomic snapshot write.
     *
     * @param int $categoryTreeId
     * @param array<int, array{code: string, parent_code: string|null}> $layout
     * @param array<string, array{code: string, name: array<int, array<string, mixed>>}> $confirmedCategories
     * @return void
     * @throws LocalizedException
     */
    public function update(int $categoryTreeId, array $layout, array $confirmedCategories): void;
}
