<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use InvalidArgumentException;

final readonly class AttributeSyncPlan
{
    public const string STATUS_READY = 'ready';
    public const string STATUS_NOOP = 'noop';
    public const string STATUS_UNSUPPORTED = 'unsupported';
    public const string STATUS_CONFLICT = 'conflict';

    /** @param array<int, MutationOperationInterface[]> $stages */
    public function __construct(
        private string $status,
        private array $stages = [],
        private string $reason = ''
    ) {
        if (count($this->stages) > 1) {
            throw new InvalidArgumentException('An attribute plan may expose only the next executable stage.');
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return array<int, MutationOperationInterface[]> */
    public function getStages(): array
    {
        return $this->stages;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
