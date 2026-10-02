<?php

declare(strict_types=1);

namespace Ergonode\Media\Test\Unit\Model\Materialization;

use Ergonode\Media\Model\Data\Asset;
use Ergonode\Media\Model\Materialization\MaterializationCache;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MaterializationCacheTest extends TestCase
{
    public function testRevisionHashStatusAndProductScopeCannotReuseAnOldPath(): void
    {
        $cache = new MaterializationCache();
        $hash = hash('sha256', 'image', true);
        $asset = $this->asset(1, $hash);
        $cache->run(function () use ($cache, $asset, $hash): void {
            $cache->remember($asset, 'product:11', 'catalog/product/first.jpg', true);
            self::assertSame('catalog/product/first.jpg', $cache->get($asset, 'product:11'));
            self::assertNull($cache->get($asset, 'product:12'));
            self::assertNull($cache->get($this->asset(2, $hash), 'product:11'));
            self::assertNull($cache->get($this->asset(1, hash('sha256', 'changed', true)), 'product:11'));
            self::assertNull($cache->get($this->asset(1, $hash, 'dirty'), 'product:11'));
            self::assertNull($cache->getShared($hash));
        });
    }

    public function testOnlyVerifiedContentCanBeSharedWithAnotherAsset(): void
    {
        $cache = new MaterializationCache();
        $hash = hash('sha256', 'image', true);
        $asset = $this->asset(1, $hash);
        $cache->run(function () use ($cache, $asset, $hash): void {
            $cache->remember($asset, 'shared:catalog/product', 'catalog/product/first.jpg');
            self::assertNull($cache->getShared($hash));
            $cache->remember($asset, 'shared:catalog/product', 'catalog/product/first.jpg', true);
            self::assertSame('catalog/product/first.jpg', $cache->getShared($hash));
        });
        self::assertNull($cache->get($asset, 'shared:catalog/product'));
        self::assertNull($cache->getShared($hash));
    }

    public function testFailureClearsBothMapsBeforeTheNextBatch(): void
    {
        $cache = new MaterializationCache();
        $hash = hash('sha256', 'image', true);
        $asset = $this->asset(1, $hash);
        try {
            $cache->run(function () use ($cache, $asset): void {
                $cache->remember($asset, 'shared:catalog/product', 'catalog/product/first.jpg', true);
                throw new RuntimeException('Interrupted batch');
            });
            self::fail('Expected an interrupted batch.');
        } catch (RuntimeException $exception) {
            self::assertSame('Interrupted batch', $exception->getMessage());
        }
        $cache->run(function () use ($cache, $asset, $hash): void {
            self::assertNull($cache->get($asset, 'shared:catalog/product'));
            self::assertNull($cache->getShared($hash));
        });
    }

    private function asset(int $revision, string $hash, string $status = 'active'): Asset
    {
        return new Asset(7, '/pictures/source.jpg', null, 'source', 'jpg', 'image/jpeg', $hash, null,
            $revision, $status);
    }
}
