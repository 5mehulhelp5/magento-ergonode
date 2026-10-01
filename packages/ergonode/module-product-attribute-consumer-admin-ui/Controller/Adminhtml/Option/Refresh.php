<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Controller\Adminhtml\Option;

use Throwable;

use Ergonode\ProductAttributeConsumerAdminUi\Model\Mapping\OptionSnapshotRefresh;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::option_refresh';

    public function __construct(
        Context $context,
        private readonly OptionSnapshotRefresh $optionSnapshotRefresh
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $mappingId = (int)$this->getRequest()->getParam('attribute_mapping_id');
        if ($mappingId <= 0) {
            return $result->setData(
                [
                'success' => false,
                'message' => (string)__('Missing attribute mapping context.'),
                ]
            );
        }

        try {
            $refresh = $this->optionSnapshotRefresh->execute($mappingId);

            return $result->setData(
                [
                'success' => true,
                'message' => (string)__('Option data has been refreshed from Ergonode.'),
                ] + $refresh
            );
        } catch (LocalizedException $exception) {
            return $result->setData(
                [
                'success' => false,
                'message' => $exception->getMessage(),
                ]
            );
        } catch (Throwable $exception) {
            return $result->setData(
                [
                'success' => false,
                'message' => (string)__('Unable to refresh Ergonode option data: %1', $exception->getMessage()),
                ]
            );
        }
    }
}
