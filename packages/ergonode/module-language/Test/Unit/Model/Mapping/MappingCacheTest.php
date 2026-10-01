<?php

declare(strict_types=1);

namespace Ergonode\Language\Test\Unit\Model\Mapping;

use Ergonode\Language\Model\Mapping\MappingCache;
use Ergonode\Language\Model\Mapping\MappingLock;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

class MappingCacheTest extends TestCase
{
    private int $now = 1000;
    private int $reads = 0;
    private string|false $entry = false;

    public function testWorkerUsesMemoryAndSeesInvalidationAtLocalDeadline(): void
    {
        $backend = $this->backend();
        $worker = $this->cache($backend);
        $writer = $this->cache($backend);
        $loads = 0;
        $current = [0 => 'pl_PL'];
        $load = static function () use (&$loads, &$current): array {
            $loads++;
            return $current;
        };
        self::assertSame($current, $worker->get($load));
        $reads = $this->reads;
        for ($index = 0; $index < 1000; $index++) {
            self::assertSame($current, $worker->get($load));
        }
        self::assertSame($reads, $this->reads);
        $current = [0 => 'en_GB'];
        $writer->invalidate();
        $this->now = 1029;
        self::assertSame([0 => 'pl_PL'], $worker->get($load));
        $this->now = 1030;
        self::assertSame($current, $worker->get($load));
        self::assertSame(2, $loads);
    }

    public function testSharedEntryAvoidsDatabaseAndRetainsItsOriginalDeadline(): void
    {
        $backend = $this->backend();
        $first = $this->cache($backend);
        $loads = 0;
        $load = static function () use (&$loads): array {
            $loads++;
            return [0 => 'pl_PL'];
        };
        $first->get($load);
        $this->now = 1899;
        $second = $this->cache($backend);
        self::assertSame([0 => 'pl_PL'], $second->get($load));
        self::assertSame(1, $loads);
        $this->now = 1900;
        $second->get($load);
        self::assertSame(2, $loads);
    }

    public function testEmptyMapIsCachedAndSameProcessInvalidationClearsMemory(): void
    {
        $cache = $this->cache($this->backend());
        $loads = 0;
        $load = static function () use (&$loads): array {
            $loads++;
            return [];
        };
        self::assertSame([], $cache->get($load));
        self::assertSame([], $cache->get($load));
        self::assertSame(1, $loads);
        $cache->invalidate();
        $cache->get($load);
        self::assertSame(2, $loads);
    }

    private function cache(CacheInterface $backend): MappingCache
    {
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturnCallback(fn (): int => $this->now);
        $manager = $this->createStub(LockManagerInterface::class);
        $manager->method('lock')->willReturn(true);
        return new MappingCache($backend, new Json(), $clock, new MappingLock($manager));
    }

    private function backend(): CacheInterface
    {
        $backend = $this->createStub(CacheInterface::class);
        $backend->method('load')->willReturnCallback(function (): string|false {
            $this->reads++;
            return $this->entry;
        });
        $backend->method('save')->willReturnCallback(function (string $value): bool {
            $this->entry = $value;
            return true;
        });
        $backend->method('remove')->willReturnCallback(function (): bool {
            $this->entry = false;
            return true;
        });
        return $backend;
    }
}
