<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Api;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;

interface CategorySynchronizationContributorInterface
{
    /**
     * Return the next executable optional synchronization stage.
     *
     * @param CategoryStateInterface $desiredState
     * @param string $mode
     * @return MutationOperationInterface[]
     */
    public function planNext(CategoryStateInterface $desiredState, string $mode): array;
}
