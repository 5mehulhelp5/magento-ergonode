<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Data;

use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use InvalidArgumentException;

final readonly class MutationVerificationResult implements MutationVerificationResultInterface
{
    private const array STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_NOT_APPLIED,
        self::STATUS_UNKNOWN,
    ];

    public function __construct(
        private string $status,
        private mixed $data = null
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported mutation verification status.');
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
