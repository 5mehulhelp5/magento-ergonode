<?php

declare(strict_types=1);

namespace Ergonode\Language\Model\Mapping;

use InvalidArgumentException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;

class MappingCache
{
    private const string KEY = 'ergonode_language_store_map_v1';

    /** @var array<int, string>|null */
    private ?array $map = null;

    private int $validUntil = 0;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly DateTime $clock,
        private readonly MappingLock $lock,
        private readonly int $cacheLifetime = 900,
        private readonly int $localCacheLifetime = 30
    ) {
        if ($cacheLifetime < 1 || $localCacheLifetime < 1 || $localCacheLifetime > $cacheLifetime) {
            throw new InvalidArgumentException('Language cache lifetimes must satisfy 1 <= local <= shared.');
        }
    }

    /**
     * @param callable(): array<int, string> $load
     * @return array<int, string>
     */
    public function get(callable $load): array
    {
        $now = $this->clock->gmtTimestamp();
        if ($this->map !== null && $now < $this->validUntil) {
            return $this->map;
        }
        $entry = $this->read();
        if ($entry === null) {
            $entry = $this->lock->run(function () use ($load): array {
                $entry = $this->read();
                if ($entry === null) {
                    $entry = [
                        'map' => $load(),
                        'expires' => $this->clock->gmtTimestamp() + $this->cacheLifetime,
                    ];
                    $this->cache->save($this->json->serialize($entry), self::KEY, [], $this->cacheLifetime);
                }
                return $entry;
            });
        }
        $this->validUntil = min($entry['expires'], $now + $this->localCacheLifetime);
        return $this->map = $entry['map'];
    }

    public function invalidate(): void
    {
        $this->map = null;
        $this->validUntil = 0;
        $this->cache->remove(self::KEY);
    }

    /** @return array{map: array<int, string>, expires: int}|null */
    private function read(): ?array
    {
        $value = $this->cache->load(self::KEY);
        if ($value === false) {
            return null;
        }
        $entry = $this->json->unserialize($value);
        if (!is_array($entry) || !is_int($entry['expires'] ?? null) || !is_array($entry['map'] ?? null)
            || $entry['expires'] <= $this->clock->gmtTimestamp()
        ) {
            return null;
        }
        $map = [];
        foreach ($entry['map'] as $storeId => $code) {
            if (!is_int($storeId) || !is_string($code)) {
                return null;
            }
            $map[$storeId] = $code;
        }
        return ['map' => $map, 'expires' => $entry['expires']];
    }
}
