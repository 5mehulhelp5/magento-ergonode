<?php

declare(strict_types=1);

namespace Ergonode\ProductConsumerAdminUi\Controller\Adminhtml\Product;

use Ergonode\ProductConsumerAdminUi\Model\ImportRequest;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class Import extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_ProductConsumer::import';

    public function __construct(
        Context $context,
        private readonly ImportRequest $service,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $data = $this->service->import(
                (string)$this->getRequest()->getParam('ids', '')
            );

            return $result->setData(['success' => true, 'items' => $data]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->logger->error('Product data import request failed.', ['exception' => $exception]);

            return $result->setData([
                'success' => false,
                'message' => (string)__(
                    'Unable to process the product import request. Check the application log.'
                ),
            ]);
        }
    }
}
