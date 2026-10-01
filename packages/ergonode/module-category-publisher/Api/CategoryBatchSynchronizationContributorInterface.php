<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Api;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;

interface CategoryBatchSynchronizationContributorInterface extends CategorySynchronizationContributorInterface
{
    /**
     * Return an isolated contributor with reads shared only by this planning round.
     * The original contributor must remain stateless between rounds and calls.
     *
     * @param array<string, CategoryStateInterface> $states Categories whose core state already matches.
     * @param string $mode
     * @return CategorySynchronizationContributorInterface
     */
    public function forBatch(array $states, string $mode): CategorySynchronizationContributorInterface;
}
