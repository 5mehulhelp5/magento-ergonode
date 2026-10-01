<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Sync;

use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;
use Ergonode\Publisher\Api\AmbiguousMutationVerifierInterface;
use Ergonode\Publisher\Api\Data\MutationOperationInterface;
use Ergonode\Publisher\Api\Data\MutationVerificationResultInterface;
use Ergonode\Publisher\Api\Exception\MutationVerificationException;
use Ergonode\Publisher\Model\Data\MutationVerificationResult;

class StrictCategoryMutationVerifier implements AmbiguousMutationVerifierInterface
{
    /** @var array<string, CategoryStateInterface|null>|null */
    private ?array $remote = null;
    /** @var array<string, bool> */
    private array $verified = [];

    /** @param string[] $codes */
    public function __construct(private readonly CategoryStateLoader $loader, private readonly array $codes)
    {
    }

    public function verify(MutationOperationInterface $operation): MutationVerificationResultInterface
    {
        $input = ($operation->getVariables()['input'] ?? null)?->getValue();
        $code = is_array($input) ? (string)($input['code'] ?? '') : '';
        if (!in_array($code, $this->codes, true)) {
            throw new MutationVerificationException(__('Category mutation has no verification owner.'));
        }
        // A repeated code starts another attempt: never reuse the previous attempt's absence.
        if ($this->remote === null || isset($this->verified[$code])) {
            $this->remote = $this->loader->loadExistenceBatch($this->codes);
            $this->verified = [];
        }
        $this->verified[$code] = true;
        $remote = $this->remote[$code] ?? null;

        return new MutationVerificationResult(
            $remote !== null
                ? MutationVerificationResultInterface::STATUS_APPLIED
                : MutationVerificationResultInterface::STATUS_NOT_APPLIED,
            $remote
        );
    }
}
