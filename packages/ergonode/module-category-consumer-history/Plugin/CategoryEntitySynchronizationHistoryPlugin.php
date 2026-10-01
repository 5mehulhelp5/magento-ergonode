<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Plugin;

use Ergonode\Category\Model\Provider\CategoryMappingQuery;
use Ergonode\CategoryConsumer\Api\CategoryEntitySynchronizerInterface;
use Ergonode\CategoryConsumerHistory\Model\Config\HistoryConfig;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;
use Ergonode\CategoryConsumerHistory\Model\Operation\OperationContext;

class CategoryEntitySynchronizationHistoryPlugin
{
    public function __construct(
        private readonly CategoryMappingQuery $mappingQuery,
        private readonly CategoryTreeHistoryCapture $capture,
        private readonly OperationContext $context,
        private readonly HistoryConfig $config
    ) {
    }

    /**
     * Record a bounded data batch; layout/reconciliation already capture nested writes.
     * @param array<int, array{category_id: int, entity: array<string, mixed>}> $operations
     * @return array{
     *     snapshots: int, snapshot_statuses: array<string, 'inserted'|'updated'|'unchanged'>,
     *     attributes: int, changed_category_ids: int[]
     * }
     */
    public function aroundSynchronize(
        CategoryEntitySynchronizerInterface $_subject,
        callable $proceed,
        array $operations
    ): array {
        if ($operations === [] || $this->context->isActive() || !$this->config->isEnabled()) {
            return $proceed($operations);
        }
        $targets = [];
        foreach ($operations as $operation) {
            $targets[(string)$operation['entity']['code']][(int)$operation['category_id']] = true;
        }
        $trees = [];
        $mappings = $this->mappingQuery->getValidMappingsByCodes(array_map('strval', array_keys($targets)));
        foreach ($mappings as $code => $rows) {
            foreach ($rows as $row) {
                if (isset($targets[$code][$row['magento_category_id']])) {
                    $trees[$row['category_tree_id']] = $row['category_tree_id'];
                }
            }
        }
        if ($trees === []) {
            return $proceed($operations);
        }

        return $this->capture->execute(
            'synchronize_data',
            array_values($trees),
            static fn (): array => $proceed($operations),
            static fn (array $result): array => [
                'status' => 'success',
                'summary' => ['attributes' => $result['attributes'], 'trees' => count($trees)],
            ]
        );
    }
}
