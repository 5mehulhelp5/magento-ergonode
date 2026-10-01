<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Controller\Adminhtml\Category\Attribute;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_mapping';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu(self::ADMIN_RESOURCE);
        $page->addBreadcrumb(__('Ergonode'), __('Ergonode'));
        $page->addBreadcrumb(__('Category Attributes'), __('Category Attributes'));
        $page->getConfig()->getTitle()->prepend(__('Category Attributes'));

        return $page;
    }
}
