<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Plugin;

use Ergonode\Category\Api\CategoryLayoutValidatorInterface;
use Ergonode\Category\Model\Sync\CategorySynchronizationLock;
use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryBatchPublisher;

class CategoryBatchValidationPlugin
{
    public function __construct(
        private readonly CategoryLayoutValidatorInterface $validator,
        private readonly CategorySynchronizationLock $lock
    ) {
    }

    /**
     * @param callable(int, array, array): array $proceed
     * @param array<int, array<string, mixed>> $items
     * @param string[] $allowExistingCodes
     * @return array<int, array<string, mixed>>
     */
    public function aroundPublish(
        CategoryBatchPublisher $subject,
        callable $proceed,
        int $categoryTreeId,
        array $items,
        array $allowExistingCodes = []
    ): array {
        return $this->lock->execute(function () use ($proceed, $categoryTreeId, $items, $allowExistingCodes): array {
            $this->validator->validate($categoryTreeId, $items);
            return $proceed($categoryTreeId, $items, $allowExistingCodes);
        });
    }
}
