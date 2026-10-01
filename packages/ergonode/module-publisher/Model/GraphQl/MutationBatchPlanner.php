<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\GraphQl;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Exception\MutationBatchCapacityException;
use Ergonode\Publisher\Api\MutationBatchBuilderInterface;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use InvalidArgumentException;

class MutationBatchPlanner implements MutationBatchPlannerInterface
{
    public function __construct(private readonly MutationBatchBuilderInterface $batchBuilder)
    {
    }

    public function plan(array $operations): array
    {
        if ($operations === []) {
            return [$this->batchBuilder->build([])];
        }

        $batches = [];
        $pending = [];

        foreach ($operations as $operation) {
            if (!$operation instanceof MutationOperationInterface) {
                throw new InvalidArgumentException('Every mutation plan item must be a mutation operation.');
            }

            try {
                $this->batchBuilder->build([...$pending, $operation]);
                $pending[] = $operation;
            } catch (MutationBatchCapacityException $exception) {
                if ($pending === []) {
                    throw $exception;
                }

                $batches[] = $this->batchBuilder->build($pending);
                $pending = [$operation];
                $this->batchBuilder->build($pending);
            }
        }

        if ($pending !== []) {
            $batches[] = $this->batchBuilder->build($pending);
        }

        return $batches;
    }
}
