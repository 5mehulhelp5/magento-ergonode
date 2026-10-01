<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\Category\Model\CategoryTree\CategoryTreeQuery;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;

class Delete extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_save';

    public function __construct(
        Action\Context $context,
        private readonly CategoryTreeQuery $categoryTreeQuery,
        private readonly CategoryTreeRepository $categoryTreeRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $categoryTreeId = (int)$this->getRequest()->getParam('category_tree_id');

        try {
            $this->categoryTreeQuery->getById($categoryTreeId);
            $this->categoryTreeRepository->deleteById($categoryTreeId);
            $this->messageManager->addSuccessMessage(__('The Category Tree has been deleted.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Exception $exception) {
            $this->messageManager->addExceptionMessage(
                $exception,
                __('Could not delete the Category Tree.')
            );
        }

        return $this->resultFactory
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('ergonode/category_tree_mapping/edit');
    }
}
