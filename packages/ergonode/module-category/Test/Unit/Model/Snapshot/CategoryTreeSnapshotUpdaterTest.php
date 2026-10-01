<?php

declare(strict_types=1);

namespace Ergonode\Category\Test\Unit\Model\Snapshot;

use Ergonode\Category\Model\Import\CategoryDetailsLoader;
use Ergonode\Category\Model\Import\CategoryNormalizer;
use Ergonode\Category\Model\Provider\CategoryCacheProvider;
use Ergonode\Category\Model\Snapshot\CategorySnapshotWriter;
use Ergonode\Category\Model\Snapshot\CategoryTreeSnapshotUpdater;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryTreeSnapshotUpdaterTest extends TestCase
{
    public function testConfirmedNamesSkipReadsAndDoNotOverwriteExistingTranslations(): void
    {
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->expects(self::once())->method('getRowsByCode')->willReturn([
            'root' => ['labels' => ['pl_PL' => 'Meble']],
        ]);
        $cache->expects(self::once())->method('clearCache');
        $loader = $this->createMock(CategoryDetailsLoader::class);
        $loader->expects(self::never())->method('load');
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::once())->method('replaceCompleteSnapshot')->with(
            7,
            self::callback(static function (array $rows): bool {
                self::assertSame(['pl_PL' => 'Meble'], $rows[0]['labels']);
                self::assertSame(['en_GB' => 'Chairs', 'pl_PL' => 'Krzesła'], $rows[1]['labels']);
                self::assertSame('root', $rows[1]['parent_code']);
                return true;
            })
        );
        (new CategoryTreeSnapshotUpdater($cache, $loader, new CategoryNormalizer(new Json()), $writer))->update(
            7,
            [['code' => 'root', 'parent_code' => null], ['code' => 'new', 'parent_code' => 'root']],
            [
                'root' => ['code' => 'root', 'name' => []],
                'new' => ['code' => 'new', 'name' => [
                    ['language' => 'pl_PL', 'value' => 'Krzesła'],
                    ['language' => 'en_GB', 'value' => 'Chairs'],
                ]],
            ]
        );
    }

    public function testReadsOnlyCategoriesWithoutMatchingCompleteConfirmation(): void
    {
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->method('getRowsByCode')->willReturn([]);
        $cache->expects(self::once())->method('clearCache');
        $loader = $this->createMock(CategoryDetailsLoader::class);
        $loader->expects(self::once())->method('load')->with(['old', 'wrong', 'incomplete'])->willReturn([
            'old' => ['code' => 'old', 'name' => []],
            'wrong' => ['code' => 'wrong', 'name' => []],
            'incomplete' => ['code' => 'incomplete', 'name' => []],
        ]);
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::once())->method('replaceCompleteSnapshot');
        (new CategoryTreeSnapshotUpdater($cache, $loader, new CategoryNormalizer(new Json()), $writer))->update(
            7,
            array_map(static fn (string $code): array => ['code' => $code, 'parent_code' => null], [
                'new', 'old', 'wrong', 'incomplete',
            ]),
            [
                'new' => ['code' => 'new', 'name' => []],
                'wrong' => ['code' => 'someone-else', 'name' => []],
                'incomplete' => ['code' => 'incomplete'],
            ]
        );
    }

    public function testPreservesExistingTranslationsAndReadsOnlyMissingCategories(): void
    {
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->expects(self::once())->method('getRowsByCode')->with(7)->willReturn([
            'root' => ['labels' => ['pl_PL' => 'Meble', 'en_GB' => 'Furniture']],
            'removed' => ['labels' => ['pl_PL' => 'Usunięta']],
        ]);
        $cache->expects(self::once())->method('clearCache');
        $loader = $this->createMock(CategoryDetailsLoader::class);
        $loader->expects(self::once())->method('load')->with(['new'])->willReturn([
            'new' => ['code' => 'new', 'name' => [['language' => 'pl_PL', 'value' => 'Zdalna nazwa']]],
        ]);
        $normalizer = new CategoryNormalizer(new Json());
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::once())->method('replaceCompleteSnapshot')->with(7, [
            $normalizer->normalizeTreeNode([
                'category' => ['code' => 'root', 'name' => [
                    ['language' => 'pl_PL', 'value' => 'Meble'],
                    ['language' => 'en_GB', 'value' => 'Furniture'],
                ]],
                'parentCategory' => null,
            ], 0),
            $normalizer->normalizeTreeNode([
                'category' => ['code' => 'new', 'name' => [['language' => 'pl_PL', 'value' => 'Zdalna nazwa']]],
                'parentCategory' => ['code' => 'root'],
            ], 1),
        ]);

        (new CategoryTreeSnapshotUpdater($cache, $loader, $normalizer, $writer))->update(7, [
            ['code' => 'root', 'parent_code' => null],
            ['code' => 'new', 'parent_code' => 'root'],
        ], []);
    }

    public function testFailedDetailsReadLeavesTheSnapshotAndCacheUntouched(): void
    {
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->expects(self::once())->method('getRowsByCode')->willReturn([]);
        $cache->expects(self::never())->method('clearCache');
        $loader = $this->createMock(CategoryDetailsLoader::class);
        $loader->expects(self::once())->method('load')->willThrowException(new LocalizedException(__('Denied')));
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::never())->method('replaceCompleteSnapshot');
        $this->expectException(LocalizedException::class);

        (new CategoryTreeSnapshotUpdater($cache, $loader, new CategoryNormalizer(new Json()), $writer))->update(
            7,
            [['code' => 'new', 'parent_code' => null]],
            []
        );
    }

    #[DataProvider('invalidLayouts')]
    public function testInvalidLayoutCannotReplaceExistingData(int $treeId, array $layout): void
    {
        $cache = $this->createMock(CategoryCacheProvider::class);
        $cache->expects(self::never())->method('getRowsByCode');
        $loader = $this->createMock(CategoryDetailsLoader::class);
        $loader->expects(self::never())->method('load');
        $writer = $this->createMock(CategorySnapshotWriter::class);
        $writer->expects(self::never())->method('replaceCompleteSnapshot');
        $this->expectException(LocalizedException::class);

        (new CategoryTreeSnapshotUpdater($cache, $loader, new CategoryNormalizer(new Json()), $writer))->update(
            $treeId,
            $layout,
            []
        );
    }

    public static function invalidLayouts(): array
    {
        return [
            [0, []],
            [7, [['code' => '', 'parent_code' => null]]],
            [7, [['code' => 'root', 'parent_code' => 'root']]],
            [7, [['code' => 'child', 'parent_code' => 'missing']]],
            [7, [['code' => 'a', 'parent_code' => null], ['code' => 'a', 'parent_code' => null]]],
        ];
    }
}
