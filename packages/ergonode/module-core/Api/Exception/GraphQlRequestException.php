<?php

declare(strict_types=1);

namespace Ergonode\Core\Api\Exception;

use InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class GraphQlRequestException extends LocalizedException implements RetryAfterExceptionInterface
{
    public const string FAILURE_REQUEST_CONSTRUCTION = 'request_construction';
    public const string FAILURE_AUTHORIZATION = 'authorization';
    public const string FAILURE_RATE_LIMIT = 'rate_limit';
    public const string FAILURE_AMBIGUOUS_TRANSPORT = 'ambiguous_transport';
    public const string FAILURE_PERMANENT_TRANSPORT = 'permanent_transport';

    private const array FAILURE_TYPES = [
        self::FAILURE_REQUEST_CONSTRUCTION,
        self::FAILURE_AUTHORIZATION,
        self::FAILURE_RATE_LIMIT,
        self::FAILURE_AMBIGUOUS_TRANSPORT,
        self::FAILURE_PERMANENT_TRANSPORT,
    ];

    public function __construct(
        string $message,
        private readonly string $failureType,
        private readonly ?int $httpStatus = null,
        private readonly ?int $retryAfterSeconds = null
    ) {
        if (!in_array($failureType, self::FAILURE_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported GraphQL request failure type.');
        }

        parent::__construct(new Phrase($message));
    }

    public function getFailureType(): string
    {
        return $this->failureType;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isTransient(): bool
    {
        return in_array($this->failureType, [
            self::FAILURE_RATE_LIMIT,
            self::FAILURE_AMBIGUOUS_TRANSPORT,
        ], true);
    }

    public function isSafeToRetry(): bool
    {
        return $this->failureType === self::FAILURE_RATE_LIMIT;
    }

    public function isAmbiguous(): bool
    {
        return $this->failureType === self::FAILURE_AMBIGUOUS_TRANSPORT;
    }
}
