<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistory\Plugin;

use Ergonode\CategoryConsumer\Api\CategoryReconciliationServiceInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationRequestInterface;
use Ergonode\CategoryConsumer\Api\Data\CategoryReconciliationResultInterface;
use Ergonode\CategoryConsumerHistory\Model\Operation\CategoryTreeHistoryCapture;

class CategoryReconciliationHistoryPlugin
{
    public function __construct(private readonly CategoryTreeHistoryCapture $historyCapture)
    {
    }

    public function aroundExecute(
        CategoryReconciliationServiceInterface $_subject,
        callable $proceed,
        CategoryReconciliationRequestInterface $request
    ): CategoryReconciliationResultInterface {
        if ($request->getMode() !== CategoryReconciliationRequestInterface::MODE_APPLY) {
            return $proceed($request);
        }
        return $this->historyCapture->execute(
            'reconcile',
            [$request->getCategoryTreeId()],
            static fn (): CategoryReconciliationResultInterface => $proceed($request),
            static fn (CategoryReconciliationResultInterface $result): array => [
                'status' => $result->getConflicts() === [] ? 'success' : 'warning',
                'summary' => $result->getStats(),
            ]
        );
    }
}
