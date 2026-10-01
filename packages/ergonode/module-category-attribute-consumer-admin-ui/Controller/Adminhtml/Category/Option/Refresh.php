<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Controller\Adminhtml\Category\Option;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeManagementProviderInterface;
use Ergonode\CategoryAttributeConsumerAdminUi\Model\CategoryOptionContextResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_refresh';

    public function __construct(
        Context $context,
        private readonly CategoryOptionContextResolver $contextResolver,
        private readonly CategoryAttributeManagementProviderInterface $managementProvider
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            return $result->setData(['success' => true] + $this->refreshOptions());
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to refresh Ergonode category options.'),
            ]);
        }
    }

    /** @return array{has_more: false, cursor: null, page_size: int} */
    private function refreshOptions(): array
    {
        $attributeCode = $this->contextResolver->getAttributeCode(
            (int)$this->getRequest()->getParam('attribute_mapping_id')
        );
        $this->managementProvider->refreshOptions($attributeCode);

        return [
            'has_more' => false,
            'cursor' => null,
            'page_size' => 200,
        ];
    }
}
