<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Reconciliation;

use Ergonode\Core\Api\Exception\GraphQlRequestException;

use function max;

class CategoryReconciliationErrorFormatter
{
    /** @return array{message: string, failure_type: string, retry_after_seconds?: int} */
    public function format(GraphQlRequestException $exception): array
    {
        $failureType = $exception->getFailureType();
        if ($failureType === GraphQlRequestException::FAILURE_RATE_LIMIT) {
            $retryAfter = max(1, $exception->getRetryAfterSeconds() ?? 5);

            return [
                'message' => $exception->getMessage(),
                'failure_type' => $failureType,
                'retry_after_seconds' => $retryAfter,
            ];
        }
        if ($failureType === GraphQlRequestException::FAILURE_AUTHORIZATION) {
            return [
                'message' => (string)__('Ergonode API authorization failed. Verify the connection configuration.'),
                'failure_type' => $failureType,
            ];
        }
        if (in_array($failureType, [
            GraphQlRequestException::FAILURE_AMBIGUOUS_TRANSPORT,
            GraphQlRequestException::FAILURE_PERMANENT_TRANSPORT,
        ], true)) {
            return [
                'message' => (string)__('Ergonode API is temporarily unavailable. Try again later.'),
                'failure_type' => $failureType,
            ];
        }

        return [
            'message' => (string)__('Unable to prepare the Ergonode category request.'),
            'failure_type' => $failureType,
        ];
    }
}
