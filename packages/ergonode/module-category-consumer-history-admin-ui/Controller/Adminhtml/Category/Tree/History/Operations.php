<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Controller\Adminhtml\Category\Tree\History;

use Ergonode\CategoryConsumerHistory\Api\CategoryTreeHistoryQueryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class Operations extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumerHistory::view';

    private const int PAGE_SIZE = 10;

    public function __construct(
        Context $context,
        private readonly CategoryTreeHistoryQueryInterface $historyQuery,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $categoryTreeId = (int)$this->getRequest()->getParam('category_tree_id');
        $beforeOperationId = (int)$this->getRequest()->getParam('before_operation_id');
        if ($categoryTreeId <= 0) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string)__('A category tree is required.'),
            ]);
        }
        try {
            return $result->setData([
                'success' => true,
                'page' => $this->historyQuery->getOperationsPage(
                    $categoryTreeId,
                    self::PAGE_SIZE,
                    $beforeOperationId > 0 ? $beforeOperationId : null
                ),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to load older Ergonode category-tree history operations.', [
                'category_tree_id' => $categoryTreeId,
                'before_operation_id' => $beforeOperationId,
                'exception' => $exception,
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string)__('Older category-tree history operations could not be loaded.'),
            ]);
        }
    }
}
