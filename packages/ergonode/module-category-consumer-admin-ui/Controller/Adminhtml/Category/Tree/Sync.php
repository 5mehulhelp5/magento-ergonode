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

class Sync extends Action implements HttpPostActionInterface
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
        $runId = (string)$this->getRequest()->getParam('run_id', '');
        $scope = (string)$this->getRequest()->getParam('synchronization_scope', 'all');
        $action = (string)$this->getRequest()->getParam('synchronization_action', 'sync');
        $resume = (bool)$this->getRequest()->getParam('resume', false);
        $this->_session->writeClose();
        try {
            // Older cached clients can still complete a request without progress controls.
            $state = $this->synchronizationRun->execute($runId ?: bin2hex(random_bytes(16)), $scope, $action, $resume);

            return $result->setData($this->response->format($state));
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'state' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
