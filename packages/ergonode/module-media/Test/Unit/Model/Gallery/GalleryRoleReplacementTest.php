<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Gallery;

use Ergonode\Media\Api\GalleryModeLockInterface;
use Ergonode\Media\Model\Config\GalleryModeProvider;
use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Gallery\GalleryWorkProcessor;
use Ergonode\Media\Model\Materialization\AssetMaterializer;
use Ergonode\Media\Model\Port\MediaRepositoryInterface;
use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Model\Gallery\GallerySynchronizer;
use Ergonode\ProductMedia\Model\Port\GalleryWriterInterface;
use Ergonode\ProductMedia\Model\Port\RoleWriterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GalleryRoleReplacementTest extends TestCase
{
    #[DataProvider('galleries')]
    public function testCurrentGalleryReplacesOldImagesAndRolesIncludingRetainedOutOfScopeRole(array $sources): void
    {
        $paths = ['a' => 'catalog/product/a.jpg', 'b' => 'catalog/product/b.jpg', 'c' => 'catalog/product/c.jpg'];
        $native = [23 => array_values($paths), 42 => [$paths['b']]];
        $nativeRoles = [0 => ['packshot' => $paths['b']], 2 => ['packshot' => $paths['b']]];
        $fileUsages = [
            ['source_path' => 'b', 'attribute_code' => 'packshot', 'store_id' => 0, 'desired' => true],
            ['source_path' => 'b', 'attribute_code' => 'packshot', 'store_id' => 2, 'desired' => true],
            ['source_path' => 'manual.pdf', 'attribute_code' => 'manual', 'store_id' => 0, 'desired' => true],
        ];
        $events = [];
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('galleryUsages')->willReturn($this->usages($sources));
        $repository->method('fileUsages')->willReturnCallback(static function () use (&$fileUsages): array { return $fileUsages; });
        $repository->expects(self::exactly(2))->method('removeFileUsage')->willReturnCallback(
            static function ($id, $code, $store, $source) use (&$fileUsages, &$events): void {
                self::assertSame(23, $id);
                self::assertSame('packshot', $code);
                self::assertSame('b', $source);
                self::assertSame(['gallery', 'roles 0', 'roles 2'], array_slice($events, 0, 3));
                $fileUsages = array_values(array_filter($fileUsages,
                    static fn (array $usage): bool => $usage['attribute_code'] !== $code || $usage['store_id'] !== $store));
                $events[] = 'cleanup';
            }
        );
        $writer = $this->createStub(GalleryWriterInterface::class);
        $writer->method('synchronize')->willReturnCallback(
            static function ($id, array $desired, array $managed) use (&$native, &$events): array {
                $wanted = array_column($desired, 'path');
                $native[$id] = array_values(array_unique([...array_diff($native[$id], $managed), ...$wanted]));
                $events[] = 'gallery';
                return array_values(array_diff($managed, $wanted));
            }
        );
        $roles = $this->createStub(RoleWriterInterface::class);
        $roles->method('write')->willReturnCallback(
            static function ($id, $store, array $values) use (&$nativeRoles, &$events): void {
                self::assertSame(23, $id);
                $nativeRoles[$store] = $values + ($nativeRoles[$store] ?? []);
                $events[] = 'roles ' . $store;
            }
        );
        $processor = $this->processor($repository, $writer, $roles);
        $write = $processor->prepare(23);
        self::assertSame($paths['b'], $nativeRoles[0]['packshot']);
        self::assertCount(3, $fileUsages);
        $write();
        self::assertSame(array_map(static fn ($p) => $paths[$p], $sources), $native[23]);
        self::assertSame([$paths['b']], $native[42]);
        self::assertNull($nativeRoles[0]['packshot']);
        self::assertNull($nativeRoles[2]['packshot']);
        self::assertSame($sources === [] ? null : $paths[$sources[0]], $nativeRoles[0]['image']);
        self::assertSame(['manual'], array_column($fileUsages, 'attribute_code'));
        self::assertSame($fileUsages, $repository->fileUsages(23));
        $processor->process(23);
        self::assertSame(array_map(static fn ($p) => $paths[$p], $sources), $native[23]);
    }

    public static function galleries(): array
    {
        return ['A and C remove B' => [['a', 'c']], 'explicit empty gallery removes all' => [[]]];
    }

    #[DataProvider('failures')]
    public function testFailedPreparationOrWriteDoesNotRemoveTheOldRoleReference(string $failure): void
    {
        $repository = $this->createMock(MediaRepositoryInterface::class);
        $repository->method('galleryUsages')->willReturn($this->usages(['a', 'c']));
        $repository->method('fileUsages')->willReturn([
            ['source_path' => 'b', 'attribute_code' => 'packshot', 'store_id' => 0, 'desired' => true],
        ]);
        $repository->expects(self::never())->method('removeFileUsage');
        $repository->expects(self::never())->method('saveGalleryPath');
        $repository->expects(self::never())->method('purgeObsoleteGalleryUsages');
        $writer = $this->createMock(GalleryWriterInterface::class);
        $roles = $this->createMock(RoleWriterInterface::class);
        if ($failure === 'gallery') {
            $writer->expects(self::once())->method('synchronize')->willThrowException(new RuntimeException('gallery failed'));
            $roles->expects(self::never())->method('write');
        } elseif ($failure === 'role') {
            $writer->expects(self::once())->method('synchronize');
            $roles->expects(self::once())->method('write')->willThrowException(new RuntimeException('role failed'));
        } else {
            $writer->expects(self::never())->method('synchronize');
            $roles->expects(self::never())->method('write');
        }
        $materializer = $failure === 'download' ? $this->createStub(AssetMaterializer::class) : null;
        $materializer?->method('shared')->willThrowException(new RuntimeException('download failed'));
        $this->expectException(RuntimeException::class);
        $this->processor($repository, $writer, $roles, $materializer)->process(23);
    }

    public static function failures(): array
    {
        return ['download failure' => ['download'], 'gallery failure' => ['gallery'], 'role failure' => ['role']];
    }

    private function usages(array $desired): array
    {
        $rows = [];
        foreach (['a', 'b', 'c'] as $index => $source) {
            $rows[] = ['asset' => new Asset($index + 1, $source, null, $source, 'jpg', 'image/jpeg', null, null, 1, 'ready'),
                'attached_path' => 'catalog/product/' . $source . '.jpg', 'desired' => in_array($source, $desired, true),
                'position' => $index + 1];
        }
        return $rows;
    }

    private function processor(MediaRepositoryInterface $repository, GalleryWriterInterface $writer,
        RoleWriterInterface $roles, ?AssetMaterializer $materializer = null): GalleryWorkProcessor
    {
        $available = $this->createStub(ImageRolesInterface::class);
        $available->method('getOptions')->willReturn(['packshot' => 'Packshot']);
        if ($materializer === null) {
            $materializer = $this->createStub(AssetMaterializer::class);
            // All successful resolutions reuse deterministic local paths.
            $materializer->method('shared')->willReturnCallback(static fn (Asset $asset): string => 'catalog/product/' . $asset->sourcePath . '.jpg');
        }
        $mode = $this->createStub(GalleryModeProvider::class);
        $mode->method('get')->willReturn('shared');
        return new GalleryWorkProcessor($repository, $mode, $this->createStub(GalleryModeLockInterface::class),
            $materializer, new GallerySynchronizer($writer, $this->createStub(GalleryRulesInterface::class), $roles, $available), $available);
    }
}
