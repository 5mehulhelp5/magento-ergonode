<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistoryAdminUi\Controller\Adminhtml\Product\Attribute\History;

use Ergonode\ProductAttributeHistory\Api\HistoryQueryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class Operations extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_ProductAttributeHistory::view';

    public function __construct(
        Context $context,
        private readonly HistoryQueryInterface $historyQuery,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $beforeId = (int)$this->getRequest()->getParam('before_id');
        try {
            return $result->setData([
                'page' => $this->historyQuery->getOperations(10, $beforeId > 0 ? $beforeId : null),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to load product attribute history operations.', ['exception' => $exception]);
            return $result->setHttpResponseCode(500)->setData([
                'message' => (string)__('History could not be loaded.'),
            ]);
        }
    }
}
