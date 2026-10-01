<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Reconciliation;

use Ergonode\CategoryConsumer\Model\Sync\CategorySynchronizationProgress;

use Ergonode\Category\Model\Mapping\CategoryMappingWriter;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Registry;

class CategoryDeletionExecutor
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryMappingWriter $categoryMappingWriter,
        private readonly MagentoCategoryProvider $magentoCategoryProvider,
        private readonly State $state,
        private readonly Registry $registry,
        private readonly CategorySynchronizationProgress $progress
    ) {
    }

    /** @param int[] $categoryIds */
    public function execute(int $categoryTreeId, array $categoryIds): int
    {
        $deleted = 0;
        foreach ($categoryIds as $categoryId) {
            $categoryId = (int)$categoryId;
            if ($categoryId <= 0) {
                continue;
            }
            $this->progress->checkpoint('deleting_categories', $deleted, count($categoryIds), (string)$categoryId);
            $this->delete($categoryId);
            $this->progress->completedOperation('deleted');
            $this->categoryMappingWriter->markMagentoCategoryDeletedById($categoryTreeId, $categoryId);
            $deleted++;
        }
        $this->magentoCategoryProvider->clearCache();

        return $deleted;
    }

    private function delete(int $categoryId): void
    {
        $secureAreaWasSet = (bool)$this->registry->registry('isSecureArea');
        if (!$secureAreaWasSet) {
            $this->registry->register('isSecureArea', true);
        }
        try {
            $this->state->emulateAreaCode(
                Area::AREA_ADMINHTML,
                fn (): bool => $this->categoryRepository->deleteByIdentifier($categoryId)
            );
        } finally {
            if (!$secureAreaWasSet) {
                $this->registry->unregister('isSecureArea');
            }
        }
    }
}
