<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Ergonode\Category\Model\CategoryTree\CategoryTreeRepository;

class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_save';

    public function __construct(
        Action\Context $context,
        private readonly CategoryTreeRepository $categoryTreeRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $this->getRequest();
        $categoryTree = $request->getPostValue();
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!is_array($categoryTree) || $categoryTree === []) {
            return $redirect->setPath('ergonode/category_tree_mapping/edit');
        }

        try {
            $categoryTreeId = $this->categoryTreeRepository->save($categoryTree);
            $this->messageManager->addSuccessMessage(__('Category Tree has been saved.'));

            return $redirect->setPath(
                'ergonode/category_tree_mapping/edit',
                ['category_tree_id' => $categoryTreeId]
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Exception $exception) {
            $this->messageManager->addExceptionMessage(
                $exception,
                __('Could not save the Category Tree.')
            );
        }

        $categoryTreeId = (int)($categoryTree['category_tree_id'] ?? 0);

        return $redirect->setPath(
            'ergonode/category_tree_mapping/edit',
            ['category_tree_id' => $categoryTreeId]
        );
    }
}
