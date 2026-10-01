<?php

declare(strict_types=1);

namespace Ergonode\Core\Model\GraphQl\Exception;

use Ergonode\Core\Api\Exception\GraphQlRequestException;

class RateLimitExceededException extends GraphQlRequestException
{
    public function __construct(
        int $retryAfterSeconds,
        int $requestsPerMinute
    ) {
        parent::__construct(
            (string)__(
                'Too Many Requests — internal Magento limit for Ergonode GraphQL: %1 requests/min. '
                . 'Try again in %2 seconds.',
                $requestsPerMinute,
                $retryAfterSeconds
            ),
            self::FAILURE_RATE_LIMIT,
            429,
            $retryAfterSeconds
        );
    }
}
