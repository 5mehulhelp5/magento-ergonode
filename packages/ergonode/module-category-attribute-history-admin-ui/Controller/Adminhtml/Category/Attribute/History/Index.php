<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistoryAdminUi\Controller\Adminhtml\Category\Attribute\History;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryAttributeHistory::view';

    private const string MENU_RESOURCE = 'Ergonode_Core::categories';

    public function execute(): Page
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(self::MENU_RESOURCE);
        $page->getConfig()->getTitle()->prepend(__('Category Attribute Mapping History'));

        return $page;
    }
}
