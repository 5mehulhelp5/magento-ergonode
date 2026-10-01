<?php

declare(strict_types=1);

namespace Ergonode\Core\Test\Unit\Model\GraphQl;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use Ergonode\Core\Model\GraphQl\Exception\RateLimitExceededException;
use Ergonode\Core\Model\GraphQl\PageQueryRetrier;

class PageQueryRetrierTest extends TestCase
{
    public function testReducesPageSizeForComplexityFailure(): void
    {
        $attempts = [];
        $retrier = new PageQueryRetrier();

        $result = $retrier->query([200, 100, 50], 200, static function (int $pageSize) use (&$attempts): array {
            $attempts[] = $pageSize;
            if ($pageSize === 200) {
                throw new LocalizedException(new Phrase('Query is too complex.'));
            }

            return ['items' => []];
        });

        self::assertSame([200, 100], $attempts);
        self::assertSame(100, $result['_page_size']);
    }

    public function testDoesNotRetryRateLimitFailure(): void
    {
        $attempts = 0;
        $retrier = new PageQueryRetrier();

        try {
            $retrier->query([200, 100, 50], 200, static function () use (&$attempts): array {
                $attempts++;
                throw new RateLimitExceededException(17, 10);
            });
            self::fail('Expected the rate limit exception to be propagated.');
        } catch (RateLimitExceededException $exception) {
            self::assertSame(17, $exception->getRetryAfterSeconds());
        }

        self::assertSame(1, $attempts);
    }

    public function testUsesSmallestPageWhenRequestedSizeIsBelowConfiguredSizes(): void
    {
        $retrier = new PageQueryRetrier();

        $result = $retrier->query(
            [50, 25, 10, 5],
            1,
            static fn (int $pageSize): array => ['selected' => $pageSize]
        );

        self::assertSame(5, $result['selected']);
        self::assertSame(5, $result['_page_size']);
    }

    public function testNormalizesPageSizesProvidedAsStringsByDependencyInjection(): void
    {
        $retrier = new PageQueryRetrier();

        $result = $retrier->query(
            ['200', '100', '50'],
            200,
            static fn (int $pageSize): array => ['selected' => $pageSize]
        );

        self::assertSame(200, $result['selected']);
        self::assertSame(200, $result['_page_size']);
    }
}
