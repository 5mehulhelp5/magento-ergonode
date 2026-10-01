<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Data;

use Ergonode\AttributePublisher\Api\Data\AttributeOptionSynchronizationResultInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use InvalidArgumentException;

final readonly class AttributeOptionSynchronizationResult implements AttributeOptionSynchronizationResultInterface
{
    private const array STATUSES = [
        self::STATUS_SUCCESS,
        self::STATUS_NOOP,
        self::STATUS_FAILED,
        self::STATUS_UNSUPPORTED,
        self::STATUS_CONFLICT,
    ];

    private const array REFERENCE_STATUSES = [
        self::REFERENCE_PRESENT,
        self::REFERENCE_ABSENT,
        self::REFERENCE_UNKNOWN,
    ];

    /**
     * @param MutationResultInterface[] $results
     */
    public function __construct(
        private string $status,
        private string $referenceStatus,
        private array $results = [],
        private ?string $message = null
    ) {
        if (!in_array($this->status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported attribute option synchronization result status.');
        }
        if (!in_array($this->referenceStatus, self::REFERENCE_STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported attribute option reference status.');
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReferenceStatus(): string
    {
        return $this->referenceStatus;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_NOOP], true);
    }
}
