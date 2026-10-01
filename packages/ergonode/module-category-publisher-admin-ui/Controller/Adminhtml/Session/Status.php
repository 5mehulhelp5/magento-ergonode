<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisherAdminUi\Controller\Adminhtml\Session;

use Ergonode\Publisher\Api\Rest\ConnectionManagementInterface;
use Ergonode\PublisherAdminUi\Api\WriteReadinessProviderInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Status extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Core::main';

    public function __construct(
        Context $context,
        private readonly ConnectionManagementInterface $connection,
        private readonly WriteReadinessProviderInterface $writeReadiness
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
            'success' => true,
            'authenticated' => $this->connection->status()['authenticated'],
            'write_readiness' => $this->writeReadiness->getStatus(),
        ]);
    }
}
