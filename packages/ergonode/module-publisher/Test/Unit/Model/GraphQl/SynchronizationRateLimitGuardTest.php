<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Test\Unit\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use PHPUnit\Framework\TestCase;

class SynchronizationRateLimitGuardTest extends TestCase
{
    public function testThrowsLargestRetryDelayFromRateLimitedMutation(): void
    {
        $first = $this->mutationWithErrors([
            ['message' => 'Too Many Requests — internal Magento limit.', 'extensions' => [
                'failure_type' => GraphQlRequestException::FAILURE_RATE_LIMIT,
                'retry_after_seconds' => 7,
            ]],
        ]);
        $second = $this->mutationWithErrors([
            ['extensions' => ['failure_type' => GraphQlRequestException::FAILURE_AUTHORIZATION]],
            ['extensions' => [
                'failure_type' => GraphQlRequestException::FAILURE_RATE_LIMIT,
                'retry_after_seconds' => 12,
            ]],
        ]);
        $result = $this->createStub(SynchronizationResultInterface::class);
        $result->method('getResults')->willReturn([$first, $second]);

        try {
            (new SynchronizationRateLimitGuard())->throwIfLimited($result);
            self::fail('Expected a rate-limit exception.');
        } catch (GraphQlRequestException $exception) {
            self::assertSame(GraphQlRequestException::FAILURE_RATE_LIMIT, $exception->getFailureType());
            self::assertSame(429, $exception->getHttpStatus());
            self::assertSame(12, $exception->getRetryAfterSeconds());
            self::assertSame('Too Many Requests — internal Magento limit.', $exception->getMessage());
        }
    }

    public function testIgnoresNonRateLimitedMutations(): void
    {
        $result = $this->createStub(SynchronizationResultInterface::class);
        $result->method('getResults')->willReturn([
            $this->mutationWithErrors([
                ['extensions' => ['failure_type' => GraphQlRequestException::FAILURE_AUTHORIZATION]],
            ]),
        ]);

        (new SynchronizationRateLimitGuard())->throwIfLimited($result);

        self::assertTrue(true);
    }

    /** @param array<int, array<string, mixed>> $errors */
    private function mutationWithErrors(array $errors): MutationResultInterface
    {
        $mutation = $this->createStub(MutationResultInterface::class);
        $mutation->method('getErrors')->willReturn($errors);

        return $mutation;
    }
}
