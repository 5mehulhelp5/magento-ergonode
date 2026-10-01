<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Api\GraphQlRequestLimiterInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\GraphQl\Exception\RateLimitExceededException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class RateLimiter implements GraphQlRequestLimiterInterface
{
    private const string CACHE_PREFIX = 'ergonode_graphql_requests_';
    private const string LOCK_NAME = 'ergonode_graphql_requests_lock';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LockManagerInterface $lockManager,
        private readonly ConfigProvider $configProvider,
        private readonly DateTime $dateTime
    ) {
    }

    public function throttle(?string $environment = null): void
    {
        $environment = $environment ?? $this->configProvider->getEnvironment();
        $limit = $this->configProvider->getRequestsPerMinute($environment);
        if ($limit === 0) {
            return;
        }

        if (!$this->lockManager->lock(self::LOCK_NAME . '_' . $environment, 5)) {
            throw new GraphQlRequestException(
                (string)__('Internal Magento limiter for Ergonode GraphQL is busy. Try again in 1 second.'),
                GraphQlRequestException::FAILURE_RATE_LIMIT,
                429,
                1
            );
        }

        try {
            $now = $this->dateTime->gmtTimestamp();
            $minute = intdiv($now, 60);
            $cacheKey = self::CACHE_PREFIX . $environment . '_' . $minute;
            $current = (int)$this->cache->load($cacheKey);

            if ($current >= $limit) {
                throw new RateLimitExceededException((($minute + 1) * 60) - $now, $limit);
            }

            $this->cache->save((string)($current + 1), $cacheKey, [], 120);
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME . '_' . $environment);
        }
    }
}
