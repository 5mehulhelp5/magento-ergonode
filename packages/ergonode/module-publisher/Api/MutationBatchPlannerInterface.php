<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api;

use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Exception\MutationBatchCapacityException;

interface MutationBatchPlannerInterface
{
    /**
     * Split operations into deterministic input-order batches.
     *
     * @param MutationOperationInterface[] $operations
     * @return MutationBatchInterface[]
     * @throws MutationBatchCapacityException When one operation cannot fit in an empty batch.
     */
    public function plan(array $operations): array;
}
