<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Api;

use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;

interface AmbiguousMutationVerifierInterface
{
    /**
     * Verify whether an operation affected remote state after an ambiguous response.
     *
     * @param MutationOperationInterface $operation
     * @return MutationVerificationResultInterface
     * @throws MutationVerificationException
     */
    public function verify(MutationOperationInterface $operation): MutationVerificationResultInterface;
}
