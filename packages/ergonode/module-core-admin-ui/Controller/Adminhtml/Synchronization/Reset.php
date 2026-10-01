<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Controller\Adminhtml\Synchronization;

use Ergonode\Core\Api\SynchronizationOperationExecutorInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class Reset extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Core::synchronizations_reset';

    public function __construct(
        Context $context,
        private readonly SynchronizationOperationExecutorInterface $operationExecutor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $processCode = trim((string)$this->getRequest()->getParam('process_code'));

        $this->_session->writeClose();

        try {
            $this->operationExecutor->resetCursor($processCode);
            $message = __('Cursor for synchronization "%1" has been reset.', $processCode);

            return $result->setData(['success' => true, 'message' => (string)$message]);
        } catch (LocalizedException $exception) {

            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to reset an Ergonode synchronization cursor from Admin.', [
                'process_code' => $processCode,
                'exception' => $exception,
            ]);
            $message = __('Unable to reset cursor for synchronization "%1".', $processCode);

            return $result->setData(['success' => false, 'message' => (string)$message]);
        }
    }
}
