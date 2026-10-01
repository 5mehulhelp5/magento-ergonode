<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Data;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationResultInterface;
use InvalidArgumentException;

final readonly class MutationResult implements MutationResultInterface
{
    private const array STATUSES = [
        self::STATUS_SUCCESS,
        self::STATUS_VALIDATION_FAILURE,
        self::STATUS_PERMANENT_FAILURE,
        self::STATUS_TRANSIENT_FAILURE,
        self::STATUS_UNRESOLVED,
    ];

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    public function __construct(
        private string $status,
        private string $alias,
        private MutationOperationInterface $operation,
        private mixed $data = null,
        private array $errors = [],
        private int $attempts = 1
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported mutation result status.');
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getOperation(): MutationOperationInterface
    {
        return $this->operation;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }
}
