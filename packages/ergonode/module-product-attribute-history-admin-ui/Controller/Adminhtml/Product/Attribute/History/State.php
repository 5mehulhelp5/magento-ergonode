<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeHistoryAdminUi\Controller\Adminhtml\Product\Attribute\History;

use Ergonode\ProductAttributeHistory\Api\HistoryQueryInterface;
use Ergonode\ProductAttributeHistoryAdminUi\Model\HistoryView;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class State extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_ProductAttributeHistory::view';

    public function __construct(
        Context $context,
        private readonly HistoryQueryInterface $historyQuery,
        private readonly LoggerInterface $logger,
        private readonly HistoryView $historyView
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $operationId = (int)$this->getRequest()->getParam('operation_id');
        if ($operationId <= 0) {
            return $result->setHttpResponseCode(400)->setData(['message' => (string)__('An operation is required.')]);
        }
        try {
            $state = $this->historyQuery->getState($operationId);
            if ($state === null) {
                return $result->setHttpResponseCode(404)->setData(['message' => (string)__('Operation not found.')]);
            }

            $side = (string)$this->getRequest()->getParam('side', '');
            if ($side !== '' && !in_array($side, ['source', 'target'], true)) {
                return $result->setHttpResponseCode(400)->setData(['message' => (string)__('Invalid side.')]);
            }
            if ($side !== '') {
                $query = trim((string)$this->getRequest()->getParam('search', ''));
                return $result->setData($query !== ''
                    ? ['parents' => $this->historyView->search($state, $side, $query)]
                    : ['options' => $this->historyView->options(
                        $state,
                        $side,
                        (string)$this->getRequest()->getParam('attribute_code', '')
                    )]);
            }
            return $result->setData(['state' => $this->historyView->summary(
                $state,
                (string)$this->getRequest()->getParam('changes_only', '1') !== '0'
            )]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to load product attribute history state.', ['exception' => $exception]);
            return $result->setHttpResponseCode(500)->setData([
                'message' => (string)__('History could not be loaded.'),
            ]);
        }
    }
}
