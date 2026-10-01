<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Data;

use Ergonode\ProductPublisher\Api\Data\ProductSynchronizationResultInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use InvalidArgumentException;

final readonly class ProductSynchronizationResult implements ProductSynchronizationResultInterface
{
    private const array STATUSES = [
        self::STATUS_SUCCESS,
        self::STATUS_NOOP,
        self::STATUS_ATTENTION,
        self::STATUS_FAILED,
        self::STATUS_CONFLICT,
        self::STATUS_BLOCKED_REFERENCE,
        self::STATUS_LOCAL_WARNING,
    ];

    /** @param MutationResultInterface[] $results @param string[] $warnings */
    public function __construct(
        private string $sku,
        private string $status,
        private array $results = [],
        private ?string $message = null,
        private array $warnings = []
    ) {
        if (!in_array($this->status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported product synchronization result status.');
        }
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /** @return string[] */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_NOOP], true);
    }
}
