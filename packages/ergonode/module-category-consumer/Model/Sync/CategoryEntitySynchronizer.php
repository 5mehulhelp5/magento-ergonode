<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Sync;

use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;

use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameSynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;
use Throwable;

class CategoryEntitySynchronizer implements CategoryEntitySynchronizerInterface, CategoryDataWorkProviderInterface
{
    public function __construct(
        private readonly CategoryNameSynchronizerInterface $nameSynchronizer,
        private readonly CategoryCacheInvalidator $cacheInvalidator,
        private readonly CategoryNameTargetProviderInterface $nameTarget
    ) {
    }

    public function hasWork(): bool
    {
        return $this->nameTarget->getAttributeCode() !== null;
    }

    public function synchronize(array $operations): array
    {
        $changed = [];
        $values = 0;
        $attempted = [];
        try {
            foreach ($operations as $operation) {
                $categoryId = (int)$operation['category_id'];
                $attempted[$categoryId] = $categoryId;
                if (isset($changed[$categoryId])) {
                    continue;
                }
                $written = $this->nameSynchronizer->synchronize($categoryId, (array)$operation['entity']['labels']);
                $values += $written;
                if ($written > 0) {
                    $changed[$categoryId] = $categoryId;
                }
            }
        } catch (Throwable $exception) {
            $this->cacheInvalidator->invalidateCategories(array_values($attempted));
            throw $exception;
        }
        if ($changed !== []) {
            $this->cacheInvalidator->invalidateCategories(array_values($changed));
        }

        return [
            'snapshots' => 0, 'snapshot_statuses' => [], 'attributes' => $values,
            'changed_category_ids' => array_values($changed),
        ];
    }
}
