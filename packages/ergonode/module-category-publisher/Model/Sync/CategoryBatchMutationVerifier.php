<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Sync;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\Publisher\Api\AmbiguousMutationVerifierInterface;
use Ergonode\Publisher\Api\MutationVerificationRoundInterface;
use Ergonode\CategoryPublisher\Api\CategorySynchronizerInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;
use Ergonode\Publisher\Model\Data\MutationVerificationResult;

class CategoryBatchMutationVerifier implements AmbiguousMutationVerifierInterface, MutationVerificationRoundInterface
{
    /** @var array<string, CategorySyncPlan> */
    private array $plans = [];
    /** @var array<string, CategoryStateInterface|null>|null */
    private ?array $remote = null;
    private ?CategorySyncPlanner $roundPlanner = null;

    public function beginVerificationRound(): void
    {
        $this->plans = [];
        $this->remote = null;
        $this->roundPlanner = null;
    }

    /**
     * @param array<string, CategoryStateInterface> $states
     * @param array<int, string> $operationOwners
     */
    public function __construct(
        private readonly CategoryStateLoader $loader,
        private readonly CategorySyncPlanner $planner,
        private readonly array $states,
        private readonly array $operationOwners,
        private readonly string $mode
    ) {
    }

    public function verify(MutationOperationInterface $operation): MutationVerificationResultInterface
    {
        $code = $this->operationOwners[spl_object_id($operation)] ?? '';
        $desired = $this->states[$code] ?? null;
        if (!$desired instanceof CategoryStateInterface) {
            throw new MutationVerificationException(__('Category mutation has no verification owner.'));
        }

        if ($this->remote === null) {
            $languages = [];
            foreach ($this->states as $stateCode => $state) {
                $languages[$stateCode] = $this->mode === CategorySynchronizerInterface::MODE_RECONCILE
                    ? [] : array_keys($state->getNames());
            }
            $this->remote = $this->loader->loadBatch($languages);
            $this->roundPlanner = $this->planner->forBatch($this->states, $this->remote, $this->mode);
        }
        $remote = $this->remote[$code];
        $plan = $this->plans[$code] ??= ($this->roundPlanner ?? $this->planner)->plan($desired, $remote, $this->mode);
        $key = (string)($operation->getMetadata()['operation_key'] ?? '');
        foreach ($plan->getStages() as $stage) {
            foreach ($stage as $pending) {
                if (($pending->getMetadata()['operation_key'] ?? null) === $key) {
                    return new MutationVerificationResult(MutationVerificationResultInterface::STATUS_NOT_APPLIED);
                }
            }
        }

        return new MutationVerificationResult(MutationVerificationResultInterface::STATUS_APPLIED, $remote);
    }
}
