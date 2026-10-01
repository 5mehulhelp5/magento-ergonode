<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Controller\Adminhtml\Readiness;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Core::readiness';

    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->addBreadcrumb(__('Ergonode'), __('Ergonode'));
        $resultPage->addBreadcrumb(__('Readiness'), __('Readiness'));
        $resultPage->getConfig()->getTitle()->prepend(__('Synchronization Readiness'));

        return $resultPage;
    }
}
