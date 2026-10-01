<?php

declare(strict_types=1);

namespace Ergonode\CategoryAdminUi\Controller\Adminhtml\Category\Tree\Mapping;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Edit extends Action
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_tree_mapping';

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->getConfig()->getTitle()->prepend(__('Category Tree Mapping'));

        return $resultPage;
    }
}
