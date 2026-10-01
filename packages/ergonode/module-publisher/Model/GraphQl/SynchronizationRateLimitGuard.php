<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\GraphQl;

use Ergonode\Core\Api\Exception\GraphQlRequestException;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;

class SynchronizationRateLimitGuard
{
    public function throwIfLimited(SynchronizationResultInterface $result): void
    {
        $retryAfter = 0;
        $messages = [];
        foreach ($result->getResults() as $mutationResult) {
            if (!$mutationResult instanceof MutationResultInterface) {
                continue;
            }
            foreach ($mutationResult->getErrors() as $error) {
                $extensions = is_array($error['extensions'] ?? null) ? $error['extensions'] : [];
                if (($extensions['failure_type'] ?? '') !== GraphQlRequestException::FAILURE_RATE_LIMIT) {
                    continue;
                }
                $retryAfter = max($retryAfter, max(1, (int)($extensions['retry_after_seconds'] ?? 5)));
                if (is_string($error['message'] ?? null) && $error['message'] !== '') {
                    $messages[] = $error['message'];
                }
            }
        }
        if ($retryAfter === 0) {
            return;
        }

        throw new GraphQlRequestException(
            $messages !== []
                ? implode(' ', array_unique($messages))
                : (string)__('Ergonode limited GraphQL requests. Retry in %1 seconds.', $retryAfter),
            GraphQlRequestException::FAILURE_RATE_LIMIT,
            429,
            $retryAfter
        );
    }
}
