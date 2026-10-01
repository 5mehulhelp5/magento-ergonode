<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Tree;

use Ergonode\CategoryPublisherAdminUi\Model\ManualTree\CategoryTreeCreator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Create extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_save';

    public function __construct(Context $context, private readonly CategoryTreeCreator $treeCreator)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $this->treeCreator->create(
                (string)$this->getRequest()->getParam('code', ''),
                (string)$this->getRequest()->getParam('name', '')
            );

            return $result->setData([
                'success' => true,
                'message' => (string)__('The Ergonode category tree has been created.'),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to create the Ergonode category tree.'),
            ]);
        }
    }
}
