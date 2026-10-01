<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Controller\Adminhtml\Category\Tree;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationRunInterface;
use Ergonode\CategoryConsumerAdminUi\Model\CategorySynchronizationResponse;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class SyncStatus extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_sync';

    public function __construct(
        Context $context,
        private readonly CategorySynchronizationRunInterface $synchronizationRun,
        private readonly CategorySynchronizationResponse $response
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $runId = (string)$this->getRequest()->getParam('run_id');
        $this->_session->writeClose();
        try {
            return $result->setData($this->response->format($this->synchronizationRun->getStatus($runId)));
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'state' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
