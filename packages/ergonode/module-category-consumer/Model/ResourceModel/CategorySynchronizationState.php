<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\ResourceModel;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationStateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/** Short-lived control state, independent of category and full-page cache tags. */
class CategorySynchronizationState implements CategorySynchronizationStateInterface
{
    private const string PREFIX = 'ergonode_category_run_';
    private const int LIFETIME = 86400;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json
    ) {
    }

    /** @return array<string, mixed>|null */
    public function get(string $runId): ?array
    {
        $value = $this->cache->load($this->key($runId));

        return $value === false ? null : $this->json->unserialize($value);
    }

    /** @param array<string, mixed> $state */
    public function save(string $runId, array $state): void
    {
        if (!$this->cache->save($this->json->serialize($state), $this->key($runId), [], self::LIFETIME)) {
            throw new LocalizedException(__('Could not store synchronization progress.'));
        }
    }

    public function requestPause(string $runId): void
    {
        if (!$this->cache->save('1', $this->key($runId) . '_pause', [], self::LIFETIME)) {
            throw new LocalizedException(__('Could not request a pause. Please try again.'));
        }
    }

    public function isPauseRequested(string $runId): bool
    {
        return $this->cache->load($this->key($runId) . '_pause') !== false;
    }

    public function clearPause(string $runId): void
    {
        $this->cache->remove($this->key($runId) . '_pause');
    }

    private function key(string $runId): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $runId)) {
            throw new LocalizedException(__('Invalid synchronization identifier.'));
        }

        return self::PREFIX . $runId;
    }
}
