<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Core\Model\Config\ConfigProvider;
use Ergonode\Core\Model\GraphQl\Exception\RateLimitExceededException;
use Ergonode\Core\Model\GraphQl\RateLimiter;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    public function testDisabledLimitDoesNotAccessSharedState(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method('load');
        $cache->expects(self::never())->method('save');
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::never())->method('lock');
        $clock = $this->createMock(DateTime::class);
        $clock->expects(self::never())->method('gmtTimestamp');

        (new RateLimiter($cache, $lock, $this->config(0), $clock))->throttle();
    }

    public function testClientsShareQuotaAndRejectedAttemptsDoNotPostponeReset(): void
    {
        $counts = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(static function (string $key) use (&$counts): string {
            return $counts[$key] ?? '0';
        });
        $cache->expects(self::exactly(11))->method('save')->willReturnCallback(
            static function (string $value, string $key) use (&$counts): bool {
                $counts[$key] = $value;
                return true;
            }
        );
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::exactly(13))->method('lock')
            ->with('ergonode_graphql_requests_lock_test', 5)->willReturn(true);
        $lock->expects(self::exactly(13))->method('unlock')->with('ergonode_graphql_requests_lock_test');
        $now = 6037;
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturnCallback(static function () use (&$now): int {
            return $now;
        });
        $first = new RateLimiter($cache, $lock, $this->config(10), $clock);
        $second = new RateLimiter($cache, $lock, $this->config(10), $clock);
        for ($request = 0; $request < 10; ++$request) {
            ($request % 2 === 0 ? $first : $second)->throttle();
        }
        foreach ([$first, $second] as $client) {
            try {
                $client->throttle();
                self::fail('The shared quota should be exhausted.');
            } catch (RateLimitExceededException $exception) {
                self::assertSame(429, $exception->getHttpStatus());
                self::assertSame(GraphQlRequestException::FAILURE_RATE_LIMIT, $exception->getFailureType());
                self::assertSame(23, $exception->getRetryAfterSeconds());
                self::assertTrue($exception->isSafeToRetry());
                self::assertStringContainsString('internal Magento limit', $exception->getMessage());
                self::assertStringContainsString('10 requests/min', $exception->getMessage());
            }
        }
        $now = 6060;
        $second->throttle();
        self::assertSame([
            'ergonode_graphql_requests_test_100' => '10',
            'ergonode_graphql_requests_test_101' => '1',
        ], $counts);
    }

    public function testWindowIsSelectedAfterWaitingForLock(): void
    {
        $locked = false;
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturnCallback(static function () use (&$locked): bool {
            $locked = true;
            return true;
        });
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturnCallback(static function () use (&$locked): int {
            self::assertTrue($locked);
            return 6060;
        });
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('save')->with('1', 'ergonode_graphql_requests_test_101', [], 120);

        (new RateLimiter($cache, $lock, $this->config(10), $clock))->throttle();
    }

    public function testBusyLockDoesNotConsumeQuota(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method('load');
        $cache->expects(self::never())->method('save');
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->method('lock')->willReturn(false);
        $lock->expects(self::never())->method('unlock');

        try {
            (new RateLimiter($cache, $lock, $this->config(10), $this->createStub(DateTime::class)))->throttle();
            self::fail('Expected a retryable local lock failure.');
        } catch (GraphQlRequestException $exception) {
            self::assertTrue($exception->isSafeToRetry());
            self::assertSame(1, $exception->getRetryAfterSeconds());
            self::assertStringContainsString('Internal Magento limiter', $exception->getMessage());
        }
    }

    public function testProductionQuotaRemainsAvailableAfterExhaustingTestQuota(): void
    {
        $counts = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('save')->willReturnCallback(static function (string $value, string $key) use (&$counts): bool {
            $counts[$key] = $value;
            return true;
        });
        $cache->method('load')->willReturnCallback(static function (string $key) use (&$counts): string {
            return $counts[$key] ?? '0';
        });
        $clock = $this->createStub(DateTime::class);
        $clock->method('gmtTimestamp')->willReturn(6000);
        $lock = $this->createStub(LockManagerInterface::class);
        $lock->method('lock')->willReturn(true);
        $limiter = new RateLimiter($cache, $lock, $this->config(1), $clock);
        $limiter->throttle('test');
        $limiter->throttle('production');
        self::assertSame([
            'ergonode_graphql_requests_test_100' => '1',
            'ergonode_graphql_requests_production_100' => '1',
        ], $counts);
        $this->expectException(RateLimitExceededException::class);
        $limiter->throttle('test');
    }

    private function config(int $limit): ConfigProvider
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getRequestsPerMinute')->willReturn($limit);
        $config->method('getEnvironment')->willReturn('test');
        return $config;
    }
}
