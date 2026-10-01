<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Sync;

use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\CategoryAttributePublisher\Api\CategoryAttributeRegistrySynchronizerInterface;
use Ergonode\CategoryAttributePublisher\Model\GraphQl\CategoryAttributeMutationFactory;
use Ergonode\Publisher\Api\MutationBatchPlannerInterface;
use Ergonode\Publisher\Api\MutationExecutorInterface;
use Ergonode\Publisher\Model\GraphQl\SynchronizationRateLimitGuard;
use Magento\Framework\Exception\LocalizedException;

class CategoryAttributeRegistrySynchronizer implements CategoryAttributeRegistrySynchronizerInterface
{
    public function __construct(
        private readonly WriteScopeCategoryAttributeCodeLoaderInterface $loader,
        private readonly CategoryAttributeMutationFactory $mutations,
        private readonly MutationBatchPlannerInterface $batchPlanner,
        private readonly MutationExecutorInterface $executor,
        private readonly SynchronizationRateLimitGuard $rateLimitGuard
    ) {
    }

    public function ensure(string $attributeCode): void
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '') {
            throw new LocalizedException(__('Ergonode category attribute code is required.'));
        }
        if (in_array($attributeCode, $this->loader->loadWriteScope(), true)) {
            return;
        }

        $operation = $this->mutations->addAllowedAttribute($attributeCode);
        $verifier = new CategoryAttributeRegistryMutationVerifier($this->loader, $attributeCode);
        foreach ($this->batchPlanner->plan([$operation]) as $batch) {
            $result = $this->executor->execute($batch, $verifier);
            $this->rateLimitGuard->throwIfLimited($result);
            if (!$result->isSuccessful()) {
                throw new LocalizedException(__(
                    'Unable to register Ergonode attribute "%1" for categories.',
                    $attributeCode
                ));
            }
        }
        if (!in_array($attributeCode, $this->loader->loadWriteScope(), true)) {
            throw new LocalizedException(__(
                'Ergonode attribute "%1" was not registered for categories.',
                $attributeCode
            ));
        }
    }
}
