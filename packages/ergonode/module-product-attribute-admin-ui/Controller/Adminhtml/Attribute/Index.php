<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::attribute_mapping';

    public function execute() : Page
    {
        /**
 * @var Page $resultPage
*/
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->addBreadcrumb(__('Ergonode'), __('Ergonode'));
        $resultPage->addBreadcrumb(__('Attributes'), __('Attributes'));
        $resultPage->getConfig()->getTitle()->prepend(__('Attributes'));

        return $resultPage;
    }
}
