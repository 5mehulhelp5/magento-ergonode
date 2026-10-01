<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Controller\Adminhtml\Category\Tree\History;

use Ergonode\CategoryConsumerHistory\Api\CategoryTreeHistoryQueryInterface;
use Ergonode\CategoryConsumerHistoryAdminUi\Model\HistoryView;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class State extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumerHistory::view';

    public function __construct(
        Context $context,
        private readonly CategoryTreeHistoryQueryInterface $historyQuery,
        private readonly LoggerInterface $logger,
        private readonly HistoryView $historyView
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $categoryTreeId = (int)$this->getRequest()->getParam('category_tree_id');
        $operationId = (int)$this->getRequest()->getParam('operation_id');
        if ($categoryTreeId <= 0) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string)__('A category tree is required.'),
            ]);
        }
        try {
            return $result->setData([
                'success' => true,
                'state' => $this->historyView->summary(
                    $this->historyQuery->getState($categoryTreeId, $operationId ?: null),
                    (string)$this->getRequest()->getParam('changes_only', '1') !== '0'
                ),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setHttpResponseCode(404)->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to load Ergonode category-tree history.', [
                'category_tree_id' => $categoryTreeId,
                'operation_id' => $operationId,
                'exception' => $exception,
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string)__('The category-tree history could not be loaded.'),
            ]);
        }
    }
}
