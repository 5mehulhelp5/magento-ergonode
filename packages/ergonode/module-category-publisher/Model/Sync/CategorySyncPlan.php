<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Sync;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use InvalidArgumentException;

final readonly class CategorySyncPlan
{
    /** @param array<int, MutationOperationInterface[]> $stages */
    public function __construct(private array $stages = [])
    {
        if (count($this->stages) > 1) {
            throw new InvalidArgumentException('A category plan may expose only the next executable stage.');
        }
    }

    /** @return array<int, MutationOperationInterface[]> */
    public function getStages(): array
    {
        return $this->stages;
    }
    public function isNoop(): bool
    {
        return $this->stages === [];
    }
}
