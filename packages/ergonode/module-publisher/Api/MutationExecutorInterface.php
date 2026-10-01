<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api;

use Ergonode\Publisher\Api\Data\MutationBatchInterface;
use Ergonode\Publisher\Api\Data\SynchronizationResultInterface;

interface MutationExecutorInterface
{
    /**
     * @param MutationBatchInterface $batch
     * @param AmbiguousMutationVerifierInterface|null $verifier
     * @return SynchronizationResultInterface
     */
    public function execute(
        MutationBatchInterface $batch,
        ?AmbiguousMutationVerifierInterface $verifier = null
    ): SynchronizationResultInterface;
}
