<?php

declare(strict_types=1);

namespace Ergonode\Category\Api;

use Magento\Framework\Exception\LocalizedException;

interface CategorySnapshotRemoverInterface
{
    /**
     * @param int $categoryTreeId
     * @param string $categoryCode
     * @return void
     * @throws LocalizedException
     */
    public function remove(int $categoryTreeId, string $categoryCode): void;
}
