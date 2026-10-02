<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Materialization;

use Ergonode\Media\Api\SharedPathStrategyInterface;
use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Index\IndexedFileResolver;
use Ergonode\Media\Model\Materialization\AssetMaterializer;
use Ergonode\Media\Model\Materialization\MaterializationCache;
use Ergonode\Media\Model\Materialization\SourcePreparer;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\Media\Model\Materialization\ProductPathStrategy;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AssetMaterializerTest extends TestCase
{
    private MediaRepositoryInterface&MockObject $repository;
    private SourcePreparer&MockObject $preparer;
    private File&MockObject $file;
    private LockManagerInterface&MockObject $locks;
    private SharedPathStrategyInterface&MockObject $strategy;
    private AssetMaterializer $materializer;
    private IndexedFileResolver&MockObject $indexedFiles;
    private MaterializationCache $cache;

    protected function setUp(): void
    {
        $this->indexedFiles = $this->createMock(IndexedFileResolver::class);
        $this->cache = new MaterializationCache();
        $this->repository = $this->createMock(MediaRepositoryInterface::class);
        $this->preparer = $this->createMock(SourcePreparer::class);
        $this->file = $this->createMock(File::class);
        $this->locks = $this->createMock(LockManagerInterface::class);
        $this->strategy = $this->createMock(SharedPathStrategyInterface::class);
        $directories = $this->createStub(DirectoryList::class);
        $directories->method('getPath')->willReturnCallback(
            static fn (string $code): string => '/root/' . $code
        );
        $products = $this->createMock(ProductPathStrategy::class);
        $products->expects(self::never())->method('resolve');
        $this->materializer = new AssetMaterializer(
            $this->repository,
            $this->preparer,
            $directories,
            $this->file,
            $this->locks,
            $this->strategy,
            $products,
            $this->indexedFiles,
            $this->cache
        );
    }

    #[DataProvider('reusableTargets')]
    public function testExistingTargetDoesNotRequireSourceCache(bool $seo, ?string $cachePath): void
    {
        $hash = hash('sha256', 'existing', true);
        $asset = $this->asset('active', $hash, $cachePath);
        $scope = $seo ? 'product:23' : 'shared:catalog/product/ergonode/shared';
        $path = 'catalog/product/ergonode/shared/existing.jpg';
        $this->repository->expects(self::once())->method('getAsset')->with(7)->willReturn($asset);
        $this->repository->expects(self::once())->method('materialization')->with(7, $scope)
            ->willReturn(['path' => $path, 'content_hash' => $hash, 'revision' => 2]);
        $this->file->expects(self::once())->method('fileExists')->with('/root/media/' . $path)->willReturn(true);
        $this->indexedFiles->expects(self::never())->method('find');
        $this->preparer->expects(self::never())->method('prepare');
        $this->file->expects(self::never())->method('cp');
        $this->locks->expects(self::never())->method('lock');
        $this->strategy->expects(self::never())->method('resolve');
        $this->repository->expects(self::never())->method('saveMaterialization');

        // The caller may hold an older snapshot; the stored asset decides reuse.
        $snapshot = $this->asset('dirty', $hash, 'removed/source.jpg');
        $actual = $seo
            ? $this->materializer->seo($snapshot, 23)
            : $this->materializer->shared($snapshot, 'catalog/product/ergonode/shared');

        self::assertSame($path, $actual);
    }

    /** @return iterable<string, array{bool, ?string}> */
    public static function reusableTargets(): iterable
    {
        yield 'shared with removed cache' => [false, 'removed/source.jpg'];
        yield 'shared without cache reference' => [false, null];
        yield 'product target with removed cache' => [true, 'removed/source.jpg'];
    }

    #[DataProvider('unusableTargets')]
    public function testUnavailableOrOutdatedTargetStillPreparesSource(
        string $status,
        bool $hasHash,
        int $storedRevision,
        bool $matchingHash,
        bool $exists
    ): void {
        $hash = hash('sha256', 'existing', true);
        $asset = $this->asset($status, $hasHash ? $hash : null, null);
        $newHash = hash('sha256', 'replacement', true);
        $prepared = $this->asset('active', $newHash, 'ergonode/media/source.jpg');
        $oldPath = 'catalog/product/ergonode/shared/existing.jpg';
        $newPath = 'catalog/product/ergonode/shared/replacement.jpg';
        $scope = 'shared:catalog/product/ergonode/shared';
        $this->repository->expects(self::once())->method('getAsset')->with(7)->willReturn($asset);
        $this->repository->method('materialization')->willReturn([
            'path' => $oldPath,
            'content_hash' => $matchingHash ? $hash : hash('sha256', 'other', true),
            'revision' => $storedRevision,
        ]);
        $this->preparer->expects(self::once())->method('prepare')->with($asset)->willReturn($prepared);
        $this->strategy->expects(self::once())->method('resolve')
            ->with('/pictures/source.jpg', $newHash, 'jpg', 'catalog/product/ergonode/shared')
            ->willReturn($newPath);
        $this->file->method('fileExists')->willReturnCallback(
            static fn (string $path): bool => $path === '/root/media/' . $oldPath && $exists
        );
        $this->file->method('dirname')->willReturn('/root/media/catalog/product/ergonode/shared');
        $this->file->expects(self::once())->method('cp')
            ->with('/root/var/ergonode/media/source.jpg', self::stringContains('/.ergonode-'))->willReturn(true);
        $this->file->expects(self::once())->method('mv')
            ->with(self::stringContains('/.ergonode-'), '/root/media/' . $newPath)->willReturn(true);
        $this->indexedFiles->expects(self::once())->method('remember')->with($newPath, $newHash);
        $this->locks->expects(self::once())->method('lock')->willReturn(true);
        $this->locks->expects(self::once())->method('unlock');
        $this->repository->expects(self::once())->method('saveMaterialization')
            ->with($prepared, $scope, null, $newPath);

        self::assertSame($newPath, $this->materializer->shared(
            $this->asset('active', $hash, null),
            'catalog/product/ergonode/shared'
        ));
    }

    public function testReusesAnIndexedFileFromAnotherSourceWithoutCopying(): void
    {
        $hash = hash('sha256', 'same-content', true);
        $asset = $this->asset('active', $hash, 'ergonode/media/source.jpg');
        $path = 'catalog/product/manual/upload.jpg';
        $this->repository->method('getAsset')->willReturn($asset);
        $this->repository->method('materialization')->willReturn(null);
        $this->preparer->expects(self::once())->method('prepare')->willReturn($asset);
        $this->strategy->expects(self::once())->method('resolve')
            ->willReturn('catalog/product/ergonode/shared/new.jpg');
        $this->locks->expects(self::once())->method('lock')->willReturn(true);
        $this->indexedFiles->expects(self::once())->method('find')->with($hash)->willReturn($path);
        $this->file->expects(self::never())->method('cp');
        $this->repository->expects(self::once())->method('saveMaterialization')
            ->with($asset, 'shared:catalog/product/ergonode/shared', null, $path);

        self::assertSame($path, $this->materializer->shared($asset, 'catalog/product/ergonode/shared'));
    }

    public function testOneHundredProductsCheckAnExistingAssetOncePerBatch(): void
    {
        $hash = hash('sha256', 'existing', true);
        $asset = $this->asset('active', $hash, null);
        $path = 'catalog/product/ergonode/shared/existing.jpg';
        $this->repository->method('getAsset')->willReturn($asset);
        $this->repository->expects(self::exactly(2))->method('materialization')->willReturn([
            'path' => $path, 'content_hash' => $hash, 'revision' => 2,
        ]);
        $this->file->expects(self::exactly(2))->method('fileExists')->willReturn(true);
        $this->preparer->expects(self::never())->method('prepare');
        $this->indexedFiles->expects(self::never())->method('find');
        $this->file->expects(self::never())->method('cp');
        $this->locks->expects(self::never())->method('lock');
        $this->strategy->expects(self::never())->method('resolve');

        $this->cache->run(function () use ($asset, $path): void {
            for ($product = 0; $product < 100; $product++) {
                self::assertSame($path, $this->materializer->shared($asset, 'catalog/product/ergonode/shared'));
            }
        });
        $this->cache->run(function () use ($asset, $path): void {
            self::assertSame($path, $this->materializer->shared($asset, 'catalog/product/ergonode/shared'));
        });
    }

    public function testNewSourceWithIdenticalContentReusesTheVerifiedPathWithinTheBatch(): void
    {
        $hash = hash('sha256', 'same-content', true);
        $first = $this->asset('active', $hash, 'ergonode/media/first.jpg');
        $second = new Asset(8, '/pictures/second.jpg', null, 'second', 'jpg', 'image/jpeg', $hash,
            'ergonode/media/second.jpg', 1, 'active');
        $path = 'catalog/product/manual/upload.jpg';
        $this->repository->method('getAsset')->willReturnCallback(
            static fn (int $id): Asset => $id === 7 ? $first : $second
        );
        $this->repository->method('materialization')->willReturn(null);
        $this->preparer->expects(self::exactly(2))->method('prepare')->willReturnArgument(0);
        $this->strategy->expects(self::exactly(2))->method('resolve')
            ->willReturn('catalog/product/ergonode/shared/new.jpg');
        $this->locks->expects(self::exactly(2))->method('lock')->willReturn(true);
        $this->locks->expects(self::exactly(2))->method('unlock');
        $this->indexedFiles->expects(self::once())->method('find')->with($hash)->willReturn($path);
        $this->file->expects(self::never())->method('cp');
        $this->repository->expects(self::exactly(2))->method('saveMaterialization');

        $this->cache->run(function () use ($first, $second, $path): void {
            self::assertSame($path, $this->materializer->shared($first, 'catalog/product/ergonode/shared'));
            self::assertSame($path, $this->materializer->shared($second, 'catalog/product/ergonode/shared'));
        });
    }

    /** @return iterable<string, array{string, bool, int, bool, bool}> */
    public static function unusableTargets(): iterable
    {
        yield 'dirty source despite caller active snapshot' => ['dirty', true, 2, true, true];
        yield 'missing source content identity' => ['active', false, 2, true, true];
        yield 'outdated target revision' => ['active', true, 1, true, true];
        yield 'different target content' => ['active', true, 2, false, true];
        yield 'missing target file' => ['active', true, 2, true, false];
    }

    private function asset(string $status, ?string $hash, ?string $cachePath): Asset
    {
        return new Asset(
            7,
            '/pictures/source.jpg',
            'https://example.test/source.jpg',
            'source',
            'jpg',
            'image/jpeg',
            $hash,
            $cachePath,
            2,
            $status
        );
    }
}
