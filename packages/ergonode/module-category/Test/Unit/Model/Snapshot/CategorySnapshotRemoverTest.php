<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Snapshot;

use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Snapshot\CategorySnapshotRemover;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CategorySnapshotRemoverTest extends TestCase
{
    public function testRemovesOnlyTheSelectedSnapshotRowAndClearsTheSourceCache(): void
    {
        $treeQuery = $this->createMock(CategoryTreeQuery::class);
        $treeQuery->expects(self::once())->method('getById')->with(7)->willReturn([
            'category_tree_id' => 7,
        ]);
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::once())->method('deleteCategory')->with(7, 'chairs')->willReturn(1);
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->expects(self::once())->method('clearCache');

        (new CategorySnapshotRemover($treeQuery, $writer, $cache))->remove(7, ' chairs ');
    }

    public function testRejectsCategoryMissingFromTheSnapshotWithoutClearingTheCache(): void
    {
        $treeQuery = $this->createStub(CategoryTreeQuery::class);
        $writer = $this->createStub(CategorySnapshotWriter::class);
        $writer->method('deleteCategory')->willReturn(0);
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->expects(self::never())->method('clearCache');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Category "chairs" is not available in the local snapshot.');

        (new CategorySnapshotRemover($treeQuery, $writer, $cache))->remove(7, 'chairs');
    }
}
