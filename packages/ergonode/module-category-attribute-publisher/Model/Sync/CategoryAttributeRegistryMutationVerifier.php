<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Sync;

use Ergonode\CategoryAttributePublisher\Api\WriteScopeCategoryAttributeCodeLoaderInterface;
use Ergonode\Publisher\Api\AmbiguousMutationVerifierInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use Ergonode\Publisher\Model\Data\MutationVerificationResult;

class CategoryAttributeRegistryMutationVerifier implements AmbiguousMutationVerifierInterface
{
    public function __construct(
        private readonly WriteScopeCategoryAttributeCodeLoaderInterface $loader,
        private readonly string $attributeCode
    ) {
    }

    public function verify(MutationOperationInterface $operation): MutationVerificationResultInterface
    {
        if (($operation->getMetadata()['operation_key'] ?? null) !== 'allowed_add:' . $this->attributeCode) {
            return new MutationVerificationResult(MutationVerificationResultInterface::STATUS_UNKNOWN);
        }
        $codes = $this->loader->loadWriteScope();

        return new MutationVerificationResult(
            in_array($this->attributeCode, $codes, true)
                ? MutationVerificationResultInterface::STATUS_APPLIED
                : MutationVerificationResultInterface::STATUS_NOT_APPLIED,
            $codes
        );
    }
}
