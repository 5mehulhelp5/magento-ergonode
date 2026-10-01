<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Controller\Adminhtml\Option;

use Ergonode\ProductAttributeConsumer\Api\OptionSynchronizationProcessInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Sync extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_ProductAttributeConsumer::option_sync';

    public function __construct(
        Context $context,
        private readonly OptionSynchronizationProcessInterface $optionSynchronizationProcess
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $mappingId = (int)$this->getRequest()->getParam('attribute_mapping_id');
        if ($mappingId <= 0) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Missing attribute mapping context.'),
            ]);
        }

        try {
            return $result->setData([
                'success' => true,
                'message' => (string)__('Options have been synchronized.'),
            ] + $this->optionSynchronizationProcess->execute($mappingId));
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to synchronize options: %1', $exception->getMessage()),
            ]);
        }
    }
}
