<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Test\Unit\Model\Reconciliation;

use Ergonode\CategoryConsumer\Model\Reconciliation\CategoryReconciliationErrorFormatter;
use Ergonode\Core\Api\Exception\GraphQlRequestException;
use PHPUnit\Framework\TestCase;

class CategoryReconciliationErrorFormatterTest extends TestCase
{
    public function testRateLimitIncludesRetryAfter(): void
    {
        $result = (new CategoryReconciliationErrorFormatter())->format(new GraphQlRequestException(
            'Too Many Requests — internal Magento limit: 10 requests/min. Try again in 20 seconds.',
            GraphQlRequestException::FAILURE_RATE_LIMIT,
            429,
            20
        ));

        self::assertSame(20, $result['retry_after_seconds']);
        self::assertStringContainsString('20', $result['message']);
        self::assertStringContainsString('internal Magento limit', $result['message']);
        self::assertSame(GraphQlRequestException::FAILURE_RATE_LIMIT, $result['failure_type']);
    }

    public function testAuthorizationAndTransportHaveControlledMessages(): void
    {
        $formatter = new CategoryReconciliationErrorFormatter();
        $authorization = $formatter->format(new GraphQlRequestException(
            'technical auth',
            GraphQlRequestException::FAILURE_AUTHORIZATION,
            401
        ));
        $transport = $formatter->format(new GraphQlRequestException(
            'technical timeout',
            GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
            504
        ));

        self::assertStringNotContainsString('technical', $authorization['message']);
        self::assertStringNotContainsString('technical', $transport['message']);
        self::assertStringContainsString('configuration', $authorization['message']);
        self::assertStringContainsString('unavailable', $transport['message']);
    }
}
