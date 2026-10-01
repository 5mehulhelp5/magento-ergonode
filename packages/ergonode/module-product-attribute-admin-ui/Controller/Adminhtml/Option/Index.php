<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Option;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Ergonode\ProductAttributeAdminUi\Block\Adminhtml\Option\Mapping;

class Index extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_mapping';

    public function execute(): Page
    {
        /**
 * @var Page $resultPage
*/
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        $resultPage->addBreadcrumb(__('Ergonode'), __('Ergonode'));
        $resultPage->addBreadcrumb(__('Options'), __('Options'));
        $resultPage->getConfig()->getTitle()->prepend(__('Options'));
        $block = $resultPage->getLayout()->createBlock(Mapping::class, 'ergonode.option.mapping');
        $resultPage->addContent($block);

        return $resultPage;
    }
}
