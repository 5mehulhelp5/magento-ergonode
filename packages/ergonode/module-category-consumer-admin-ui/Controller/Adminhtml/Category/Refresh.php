<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerAdminUi\Controller\Adminhtml\Category;

use Ergonode\CategoryConsumer\Api\CategoryEntityRefresherInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_sync';

    public function __construct(
        Context $context,
        private readonly CategoryEntityRefresherInterface $categoryRefresher,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $categoryId = (int)$this->getRequest()->getParam('category_id', 0);

        try {
            $stats = $this->categoryRefresher->refresh($categoryId);
            $message = (string)__('Category refreshed from Ergonode.');
            $this->messageManager->addSuccessMessage($message);

            return $result->setData([
                'success' => true,
                'message' => $message,
                'reload' => true,
            ] + $stats);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to refresh a Magento category from Ergonode.', [
                'magento_category_id' => $categoryId,
                'exception' => $exception,
            ]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to refresh the category from Ergonode.'),
            ]);
        }
    }
}
