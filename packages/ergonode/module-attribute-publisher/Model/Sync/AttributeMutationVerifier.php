<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\Publisher\Api\MutationVerificationRoundInterface;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;
use Throwable;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use Ergonode\Publisher\Model\Data\MutationVerificationResult;

class AttributeMutationVerifier implements MutationVerificationRoundInterface
{
    private ?AttributeSyncPlan $plan = null;

    private ?AttributeStateInterface $remote = null;

    private ?MutationVerificationException $failure = null;

    public function __construct(
        private readonly AttributeStateLoader $loader,
        private readonly AttributeSyncPlanner $planner,
        private readonly AttributeStateInterface $desired,
        private readonly string $mode
    ) {
    }

    public function verify(MutationOperationInterface $operation): MutationVerificationResultInterface
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->plan === null) {
            try {
                $this->remote = $this->loader->load(
                    $this->desired->getCode(),
                    $this->planner->getReadLanguages($this->desired, $this->mode)
                );
                $this->plan = $this->planner->plan($this->desired, $this->remote, $this->mode);
            } catch (Throwable $exception) {
                $this->failure = new MutationVerificationException(
                    __('Unable to verify the attribute mutation: %1', $exception->getMessage()),
                    $exception
                );
                throw $this->failure;
            }
        }
        $key = (string)($operation->getMetadata()['operation_key'] ?? '');
        foreach ($this->plan->getStages() as $stage) {
            foreach ($stage as $pending) {
                if (($pending->getMetadata()['operation_key'] ?? null) === $key) {
                    return new MutationVerificationResult(MutationVerificationResultInterface::STATUS_NOT_APPLIED);
                }
            }
        }
        return new MutationVerificationResult(MutationVerificationResultInterface::STATUS_APPLIED, $this->remote);
    }

    public function beginVerificationRound(): void
    {
        $this->plan = null;
        $this->remote = null;
        $this->failure = null;
    }
}
