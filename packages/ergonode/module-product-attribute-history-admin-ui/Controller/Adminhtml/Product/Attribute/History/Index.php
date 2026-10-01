<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistoryAdminUi\Controller\Adminhtml\Product\Attribute\History;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_ProductAttributeHistory::view';

    private const string MENU_RESOURCE = 'Ergonode_Core::products';

    public function execute(): Page
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(self::MENU_RESOURCE);
        $page->getConfig()->getTitle()->prepend(__('Product Attribute Mapping History'));

        return $page;
    }
}
