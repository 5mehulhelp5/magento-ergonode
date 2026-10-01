<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumerHistoryAdminUi\Controller\Adminhtml\Category\Tree\History;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumerHistory::view';

    private const string MENU_RESOURCE = 'Ergonode_Core::categories';

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu(self::MENU_RESOURCE);
        $resultPage->getConfig()->getTitle()->prepend(__('Category Tree History'));

        return $resultPage;
    }
}
