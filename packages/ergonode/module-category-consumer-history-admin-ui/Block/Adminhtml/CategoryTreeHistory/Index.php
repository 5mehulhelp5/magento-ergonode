<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Block\Adminhtml\CategoryTreeHistory;

use Ergonode\CategoryConsumerHistory\Api\CategoryTreeHistoryQueryInterface;
use Ergonode\CategoryConsumerHistoryAdminUi\Model\HistoryView;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Index extends Template
{
    private const int OPERATIONS_PAGE_SIZE = 10;

    /** @var array<string, mixed>|null */
    private ?array $config = null;

    public function __construct(
        Context $context,
        private readonly CategoryTreeHistoryQueryInterface $historyQuery,
        private readonly Json $json,
        private readonly HistoryView $historyView,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize($this->getConfig());
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }
        $trees = $this->historyQuery->getTrees();
        $requestedTreeId = (int)$this->getRequest()->getParam('category_tree_id');
        $selectedTreeId = $this->resolveTreeId($trees, $requestedTreeId);
        $operationsPage = $selectedTreeId > 0
            ? $this->historyQuery->getOperationsPage($selectedTreeId, self::OPERATIONS_PAGE_SIZE)
            : [
                'items' => [],
                'total' => 0,
                'page_size' => self::OPERATIONS_PAGE_SIZE,
                'has_more' => false,
                'next_before_id' => null,
            ];
        $operations = $operationsPage['items'];
        $requestedOperationId = (int)$this->getRequest()->getParam('operation_id');
        $selectedOperation = $this->resolveOperation($operations, $requestedOperationId, $selectedTreeId);
        $selectedOperationId = (int)($selectedOperation['operation_id'] ?? 0);
        $state = $selectedTreeId > 0
            ? $this->historyQuery->getState($selectedTreeId, $selectedOperationId ?: null)
            : null;
        foreach ($trees as &$tree) {
            $tree['history_url'] = $this->getUrl('ergonode/category_tree_history/index', [
                'category_tree_id' => (int)$tree['category_tree_id'],
            ]);
        }
        unset($tree);

        $this->config = [
            'trees' => $trees,
            'category_tree_id' => $selectedTreeId,
            'selected_operation_id' => $selectedOperationId ?: null,
            'selected_operation' => $selectedOperation,
            'open_details' => (bool)$this->getRequest()->getParam('details'),
            'operations' => $operations,
            'operations_pagination' => [
                'total' => $operationsPage['total'],
                'page_size' => $operationsPage['page_size'],
                'has_more' => $operationsPage['has_more'],
                'next_before_id' => $operationsPage['next_before_id'],
            ],
            'state' => $this->historyView->summary($state),
            'urls' => [
                'state' => $this->getUrl('ergonode/category_tree_history/state'),
                'operations' => $this->getUrl('ergonode/category_tree_history/operations'),
                'mapping' => $this->getUrl('ergonode/category_tree_mapping/edit', [
                    'category_tree_id' => $selectedTreeId,
                ]),
            ],
        ];

        return $this->config;
    }

    /** @param list<array<string, int|string|bool>> $trees */
    private function resolveTreeId(array $trees, int $requestedTreeId): int
    {
        $treeIds = array_map(static fn (array $tree): int => (int)$tree['category_tree_id'], $trees);

        return in_array($requestedTreeId, $treeIds, true) ? $requestedTreeId : ($treeIds[0] ?? 0);
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @return array<string, mixed>|null
     */
    private function resolveOperation(
        array $operations,
        int $requestedOperationId,
        int $categoryTreeId
    ): ?array {
        foreach ($operations as $operation) {
            if ((int)$operation['operation_id'] === $requestedOperationId) {
                return $operation;
            }
        }
        if ($categoryTreeId > 0 && $requestedOperationId > 0 && $requestedOperationId < PHP_INT_MAX) {
            $page = $this->historyQuery->getOperationsPage($categoryTreeId, 1, $requestedOperationId + 1);
            $operation = $page['items'][0] ?? null;
            if ($operation !== null && (int)$operation['operation_id'] === $requestedOperationId) {
                return $operation;
            }
        }

        return $operations[0] ?? null;
    }
}
