<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Model\Sync;

use Ergonode\Publisher\Api\Data\MutationResultInterface;

class ProductMutationFailureClassifier
{
    private const array IDEMPOTENT_CREATE_KEYS = [
        'categories:add',
        'variant:add',
    ];

    public function isCreateConflict(MutationResultInterface $result): bool
    {
        return $this->operationKey($result) === 'create' && $this->isAlreadyExists($result);
    }

    public function isAssignedCreateConflict(MutationResultInterface $result): bool
    {
        return $this->operationKey($result) === 'identity:create' && $this->isAlreadyExists($result);
    }

    public function isNotFoundResult(MutationResultInterface $result): bool
    {
        return $this->isNotFound($result);
    }

    public function isIdempotentConflict(MutationResultInterface $result): bool
    {
        $key = $this->operationKey($result);

        return (in_array($key, self::IDEMPOTENT_CREATE_KEYS, true) && $this->isAlreadyExists($result))
            || ($key === 'delete' && $this->isNotFound($result))
            || (str_starts_with($key, 'value_delete:') && $this->isNotFound($result));
    }

    public function needsGroupedQuantityFallback(MutationResultInterface $result): bool
    {
        return str_starts_with($this->operationKey($result), 'child:add:')
            && $this->isAlreadyExists($result);
    }

    private function isAlreadyExists(MutationResultInterface $result): bool
    {
        foreach ($result->getErrors() as $error) {
            $code = strtoupper(trim((string)($error['extensions']['code'] ?? '')));
            $message = strtolower(trim((string)($error['message'] ?? '')));
            if (in_array($code, ['ALREADY_EXISTS', 'CONFLICT'], true)
                || str_contains($message, 'already exists')
                || str_contains($message, 'already assigned')
                || str_contains($message, 'has already been taken')
            ) {
                return true;
            }
        }

        return false;
    }

    private function isNotFound(MutationResultInterface $result): bool
    {
        foreach ($result->getErrors() as $error) {
            $code = strtoupper(trim((string)($error['extensions']['code'] ?? '')));
            $message = strtolower(trim((string)($error['message'] ?? '')));
            if ($code === 'NOT_FOUND' || str_contains($message, 'not found')) {
                return true;
            }
        }

        return false;
    }

    private function operationKey(MutationResultInterface $result): string
    {
        return (string)($result->getOperation()->getMetadata()['operation_key'] ?? '');
    }
}
