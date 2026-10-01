<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Controller\Adminhtml\Category\Tree;

use Ergonode\CategoryConsumer\Api\CategorySynchronizationActionInterface;
use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;

class ResetSyncCursor extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_sync';

    public function __construct(
        Action\Context $context,
        private readonly CategorySynchronizationActionInterface $cursorResetter,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $this->cursorResetter->execute(
                (string)$this->getRequest()->getParam('synchronization_scope', 'tree'),
                'reset-cursor'
            );

            return $result->setData([
                'success' => true,
                'message' => (string)__('Cursor reset. The next Sync will start from the beginning.'),
            ]);
        } catch (Exception $exception) {
            $this->logger->error('Could not reset the synchronization cursor.', [
                'exception' => $exception,
            ]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('Could not reset the synchronization cursor.'),
            ]);
        }
    }
}
