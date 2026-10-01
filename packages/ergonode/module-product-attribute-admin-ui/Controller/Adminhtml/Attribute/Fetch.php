<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Controller\Adminhtml\Attribute;

use Ergonode\ProductAttributeAdminUi\Model\Mapping\RemoteAttributeMetadataSource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Fetch extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::attribute_mapping';

    public function __construct(
        Context $context,
        private readonly RemoteAttributeMetadataSource $source
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            return $result->setData(['success' => true] + $this->source->refresh());
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to fetch Ergonode attributes.'),
            ]);
        }
    }
}
