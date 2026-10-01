<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;

class Reorder extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_save';

    public function __construct(
        Context $context,
        private readonly CategoryTreeRepository $categoryTreeRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $this->reorderTrees();

            return $result->setData([
                'success' => true,
                'message' => (string)__('Category Tree order has been saved.'),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to save the Category Tree order.'),
            ]);
        }
    }

    private function reorderTrees(): void
    {
        $categoryTreeIds = $this->getRequest()->getParam('category_tree_ids', []);
        if (!is_array($categoryTreeIds)) {
            throw new LocalizedException(__('The Category Tree order is invalid.'));
        }
        $this->categoryTreeRepository->reorder(array_map('intval', $categoryTreeIds));
    }
}
