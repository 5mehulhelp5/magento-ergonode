<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Mapping;

use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumer\Api\CategoryWriteTransactionInterface;
use Ergonode\CategoryConsumer\Model\Provider\CategoryDataMappingProvider;
use Ergonode\CategoryConsumer\Model\Sync\CategoryCacheInvalidator;

class CategoryMappingDataWriter
{
    public function __construct(
        private readonly CategoryWriteTransactionInterface $transaction,
        private readonly CategoryEntitySynchronizerInterface $synchronizer,
        private readonly CategoryCacheInvalidator $cacheInvalidator,
        private readonly CategoryDataMappingProvider $mappingProvider
    ) {
    }

    /**
     * @param callable(): array{updated: int, unchanged: int, attribute_values: int} $saveLayout
     * @param list<array{category_id: int, entity: array<string, mixed>}> $operations
     * @return array{updated: int, unchanged: int, attribute_values: int}
     */
    public function save(int $categoryTreeId, callable $saveLayout, array $operations): array
    {
        return $this->cacheInvalidator->defer(fn (): array => $this->transaction->execute(
            function () use ($categoryTreeId, $saveLayout, $operations): array {
                $stats = $saveLayout();
                $operations = $this->mappingProvider->filterForTree($categoryTreeId, $operations);
                $stats['attribute_values'] = $this->synchronizer->synchronize($operations)['attributes'];
                return $stats;
            }
        ));
    }
}
