<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api;

use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;

interface MutationBatchBuilderInterface
{
    /**
     * @param MutationOperationInterface[] $operations
     * @return MutationBatchInterface
     */
    public function build(array $operations): MutationBatchInterface;
}
