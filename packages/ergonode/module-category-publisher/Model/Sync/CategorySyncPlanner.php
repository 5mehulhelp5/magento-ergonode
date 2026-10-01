<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Sync;

use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\CategoryPublisher\Api\CategoryBatchSynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizationContributorInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\CategoryPublisher\Model\GraphQl\CategoryMutationFactory;
use InvalidArgumentException;

class CategorySyncPlanner
{
    /** @var CategorySynchronizationContributorInterface[] */
    private array $contributors;

    /** @param CategorySynchronizationContributorInterface[] $contributors */
    public function __construct(
        private readonly CategoryMutationFactory $mutations,
        array $contributors = []
    ) {
        foreach ($contributors as $contributor) {
            if (!$contributor instanceof CategorySynchronizationContributorInterface) {
                throw new InvalidArgumentException(
                    'Category synchronization contributors must implement their API contract.'
                );
            }
        }
        $this->contributors = array_values($contributors);
    }

    /**
     * @param array<string, CategoryStateInterface> $states
     * @param array<string, CategoryStateInterface|null> $remote
     */
    public function forBatch(array $states, array $remote, string $mode): self
    {
        $eligible = [];
        if ($mode !== CategorySynchronizerInterface::MODE_CREATE_STRICT) {
            foreach ($states as $code => $state) {
                if (!$state->isDeleted() && isset($remote[$code])
                    && $state->getNames() === $remote[$code]->getNames()
                ) {
                    $eligible[$code] = $state;
                }
            }
        }
        $planner = clone $this;
        $planner->contributors = [];
        foreach ($this->contributors as $contributor) {
            $planner->contributors[] = $eligible !== []
                && $contributor instanceof CategoryBatchSynchronizationContributorInterface
                ? $contributor->forBatch($eligible, $mode) : $contributor;
        }
        return $planner;
    }

    public function plan(
        CategoryStateInterface $desired,
        ?CategoryStateInterface $remote,
        string $mode
    ): CategorySyncPlan {
        if ($remote === null) {
            return $desired->isDeleted()
                ? new CategorySyncPlan()
                : new CategorySyncPlan([[$this->mutations->create($desired)]]);
        }
        if ($desired->isDeleted()) {
            return $mode === CategorySynchronizerInterface::MODE_RECONCILE
                ? new CategorySyncPlan([[$this->mutations->delete($desired->getCode())]])
                : new CategorySyncPlan();
        }
        if ($mode === CategorySynchronizerInterface::MODE_CREATE_STRICT) {
            return new CategorySyncPlan();
        }

        if ($desired->getNames() !== $remote->getNames()) {
            return new CategorySyncPlan([[$this->mutations->setName($desired)]]);
        }
        foreach ($this->contributors as $contributor) {
            $stage = $contributor->planNext($desired, $mode);
            if ($stage !== []) {
                return new CategorySyncPlan([$stage]);
            }
        }

        return new CategorySyncPlan();
    }
}
