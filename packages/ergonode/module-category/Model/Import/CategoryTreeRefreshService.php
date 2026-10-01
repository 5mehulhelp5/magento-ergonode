<?php

declare(strict_types=1);

namespace Ergonode\Category\Model\Import;

use Ergonode\Category\Model\Import\FreshCategoryTreeLoader;
use Ergonode\Category\Api\CategoryTreeRefreshServiceInterface;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;

class CategoryTreeRefreshService implements CategoryTreeRefreshServiceInterface
{
    public function __construct(
        private readonly FreshCategoryTreeLoader $freshCategoryTreeLoader,
        private readonly CategoryCacheProvider $categoryCacheProvider,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly CategorySynchronizationLock $synchronizationLock
    ) {
    }

    public function refresh(int $categoryTreeId): array
    {
        return $this->synchronizationLock->execute(
            fn (): array => $this->refreshUnlocked($categoryTreeId)
        );
    }

    /** @return array<string, mixed> */
    private function refreshUnlocked(int $categoryTreeId): array
    {
        $result = $this->freshCategoryTreeLoader->load($categoryTreeId);
        $this->categoryCacheProvider->clearCache();
        $this->magentoCategoryProvider->clearCache();

        return $result;
    }
}
