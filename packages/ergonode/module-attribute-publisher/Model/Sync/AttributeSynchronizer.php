<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model\Sync;

use Ergonode\AttributePublisher\Api\AttributeSynchronizerInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeStateInterface;
use Ergonode\AttributePublisher\Api\Data\AttributeSynchronizationResultInterface;
use Ergonode\AttributePublisher\Model\Data\AttributeSynchronizationResult;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Magento\Framework\Exception\LocalizedException;

class AttributeSynchronizer implements AttributeSynchronizerInterface
{
    private const int MAX_STAGES = 20;

    public function __construct(
        private readonly AttributeStateLoader $loader,
        private readonly AttributeSyncPlanner $syncPlanner,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor
    ) {
    }

    public function synchronize(
        AttributeStateInterface $desiredState,
        string $mode = self::MODE_UPDATE,
        ?AttributeStateInterface $initialState = null
    ): AttributeSynchronizationResultInterface {
        if (!in_array($mode, [self::MODE_CREATE_ONLY, self::MODE_UPDATE, self::MODE_RECONCILE], true)) {
            throw new LocalizedException(__('Unsupported attribute synchronization mode "%1".', $mode));
        }
        if ($initialState !== null && $initialState->getCode() !== $desiredState->getCode()) {
            throw new LocalizedException(__('Initial attribute state belongs to a different code.'));
        }
        $results = [];
        $languages = $this->syncPlanner->getReadLanguages($desiredState, $mode);
        for ($step = 0; $step < self::MAX_STAGES; ++$step) {
            $remote = $step === 0 && $initialState !== null
                ? $initialState : $this->loader->load($desiredState->getCode(), $languages);
            $plan = $this->syncPlanner->plan($desiredState, $remote, $mode);
            if ($plan->getStatus() === AttributeSyncPlan::STATUS_NOOP) {
                return new AttributeSynchronizationResult(
                    $results === []
                        ? AttributeSynchronizationResultInterface::STATUS_NOOP
                        : AttributeSynchronizationResultInterface::STATUS_SUCCESS,
                    AttributeSynchronizationResultInterface::REFERENCE_PRESENT,
                    $results
                );
            }
            if ($plan->getStatus() === AttributeSyncPlan::STATUS_UNSUPPORTED) {
                return new AttributeSynchronizationResult(
                    AttributeSynchronizationResultInterface::STATUS_UNSUPPORTED,
                    $remote === null
                        ? AttributeSynchronizationResultInterface::REFERENCE_ABSENT
                        : AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN,
                    $results,
                    $plan->getReason()
                );
            }
            if ($plan->getStatus() === AttributeSyncPlan::STATUS_CONFLICT) {
                return new AttributeSynchronizationResult(
                    AttributeSynchronizationResultInterface::STATUS_CONFLICT,
                    AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN,
                    $results,
                    $plan->getReason()
                );
            }
            $stage = $plan->getStages()[0] ?? [];
            $verifier = new AttributeMutationVerifier(
                $this->loader,
                $this->syncPlanner,
                $desiredState,
                $mode
            );
            foreach ($this->batchPlanner->plan($stage) as $batch) {
                $stageResult = $this->executor->execute($batch, $verifier);
                foreach ($stageResult->getResults() as $result) {
                    $results[] = $result;
                }
                if (!$stageResult->isSuccessful()) {
                    return new AttributeSynchronizationResult(
                        AttributeSynchronizationResultInterface::STATUS_FAILED,
                        AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN,
                        $results,
                        'Attribute mutation execution failed.'
                    );
                }
            }
        }
        return new AttributeSynchronizationResult(
            AttributeSynchronizationResultInterface::STATUS_FAILED,
            AttributeSynchronizationResultInterface::REFERENCE_UNKNOWN,
            $results,
            (string)__('Attribute synchronization did not converge after %1 stages.', self::MAX_STAGES)
        );
    }
}
