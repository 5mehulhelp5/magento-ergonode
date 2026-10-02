<?php

declare(strict_types=1);

namespace Ergonode\ProductMedia\Test\Unit\Model\Gallery;

use Ergonode\ProductMedia\Api\GalleryRulesInterface;
use Ergonode\ProductMedia\Api\ImageRolesInterface;
use Ergonode\ProductMedia\Model\Gallery\GallerySynchronizer;
use Ergonode\ProductMedia\Model\Port\GalleryWriterInterface;
use Ergonode\ProductMedia\Model\Port\RoleWriterInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GallerySynchronizerTest extends TestCase
{
    public function testRolesFollowCompletedDeduplicatedGallery(): void
    {
        $rules = $this->createStub(GalleryRulesInterface::class);
        $rules->method('getAdditionalRole')->willReturn(['attribute' => 'hover_image', 'position' => 2]);
        $available = $this->createStub(ImageRolesInterface::class);
        $available->method('getOptions')->willReturn(['hover_image' => 'Hover']);
        $gallery = $this->createMock(GalleryWriterInterface::class);
        $done = false;
        $gallery->expects(self::once())->method('synchronize')->with(8, [
            ['path' => 'catalog/product/a.jpg', 'position' => 1],
            ['path' => 'catalog/product/b.jpg', 'position' => 2],
        ], [])->willReturnCallback(static function () use (&$done): void {
            $done = true;
        });
        $roles = $this->createMock(RoleWriterInterface::class);
        $roles->expects(self::once())->method('write')->with(8, 0, [
            'image' => 'catalog/product/a.jpg', 'small_image' => 'catalog/product/a.jpg',
            'thumbnail' => 'catalog/product/a.jpg', 'hover_image' => 'catalog/product/b.jpg',
        ])->willReturnCallback(static function () use (&$done): void {
            self::assertTrue($done);
        });
        (new GallerySynchronizer($gallery, $rules, $roles, $available))->synchronize(8, [
            ['path' => 'catalog/product/b.jpg', 'position' => 3],
            ['path' => 'catalog/product/a.jpg', 'position' => 1],
            ['path' => 'catalog/product/a.jpg', 'position' => 2],
        ], []);
    }
    public function testGalleryFailureNeverClearsRoles(): void
    {
        $gallery = $this->createMock(GalleryWriterInterface::class);
        $gallery->expects(self::once())->method('synchronize')
            ->willThrowException(new RuntimeException('failed gallery'));
        $roles = $this->createMock(RoleWriterInterface::class);
        $roles->expects(self::never())->method('write');
        $this->expectException(RuntimeException::class);
        (new GallerySynchronizer(
            $gallery,
            $this->createStub(GalleryRulesInterface::class),
            $roles,
            $this->createStub(ImageRolesInterface::class)
        ))->synchronize(8, [], ['catalog/product/old.jpg']);
    }
    public function testEmptyCompletedGalleryClearsPrimaryRoles(): void
    {
        $roles = $this->createMock(RoleWriterInterface::class);
        $roles->expects(self::once())->method('write')->with(8, 0, ['image' => null, 'small_image' => null,
            'thumbnail' => null]);
        (new GallerySynchronizer(
            $this->createStub(GalleryWriterInterface::class),
            $this->createStub(GalleryRulesInterface::class),
            $roles,
            $this->createStub(ImageRolesInterface::class)
        ))->synchronize(8, [], []);
    }
}
