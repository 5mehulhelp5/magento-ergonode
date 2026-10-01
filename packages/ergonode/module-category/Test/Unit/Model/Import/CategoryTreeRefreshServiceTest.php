<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Import;

use Ergonode\Category\Model\Import\FreshCategoryTreeLoader;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Import\CategoryTreeRefreshService;
use Ergonode\Category\Model\Provider\MagentoCategoryProvider;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryTreeRefreshServiceTest extends TestCase
{
    public function testRefreshesSnapshotAndClearsBothReadModels(): void
    {
        $result = [
            'complete' => true,
            'pages' => 1,
            'page_size' => 200,
            'categories' => [],
            'snapshot' => ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0],
        ];
        $loader = $this->createMock(FreshCategoryTreeLoader::class);
        $loader->expects(self::once())->method('load')->with(7)->willReturn($result);
        $categoryCache = $this->createMock(CategoryCacheProvider::class);
        $categoryCache->expects(self::once())->method('clearCache');
        $magentoCategories = $this->createMock(MagentoCategoryProvider::class);
        $magentoCategories->expects(self::once())->method('clearCache');
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects(self::once())->method('lock')->willReturn(true);
        $lockManager->expects(self::once())->method('unlock');
        self::assertSame(
            $result,
            (new CategoryTreeRefreshService(
                $loader,
                $categoryCache,
                $magentoCategories,
                new CategorySynchronizationLock($lockManager),
            ))->refresh(7)
        );
    }
}
