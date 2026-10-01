<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Snapshot;

use Ergonode\Category\Api\CategorySnapshotRemoverInterface;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Magento\Framework\Exception\LocalizedException;

class CategorySnapshotRemover implements CategorySnapshotRemoverInterface
{
    public function __construct(
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategorySnapshotWriter $snapshotWriter,
        private readonly CategoryCacheProvider $categoryCacheProvider
    ) {
    }

    public function remove(int $categoryTreeId, string $categoryCode): void
    {
        $categoryCode = trim($categoryCode);
        if ($categoryTreeId <= 0 || $categoryCode === '') {
            throw new LocalizedException(__('Category Tree and snapshot category are required.'));
        }

        $this->categoryTreeQuery->getById($categoryTreeId);
        if ($this->snapshotWriter->deleteCategory($categoryTreeId, $categoryCode) !== 1) {
            throw new LocalizedException(
                __('Category "%1" is not available in the local snapshot.', $categoryCode)
            );
        }

        $this->categoryCacheProvider->clearCache();
    }
}
