<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Controller\Adminhtml\Synchronization;

use Ergonode\Core\Api\SynchronizationMonitorInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

class Status extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Core::synchronizations';

    public function __construct(Context $context, private readonly SynchronizationMonitorInterface $monitor)
    {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $this->_session->writeClose();
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHeader('Cache-Control', 'no-store', true);

        return $result->setData([
            'success' => true,
            'observed_at' => gmdate('c'),
            'processes' => $this->monitor->getList(),
        ]);
    }
}
