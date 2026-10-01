<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Controller\Adminhtml\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionManagementInterface;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Status extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Publisher::rest_connection';

    public function __construct(Context $context, private readonly ConnectionManagementInterface $connection)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            return $result->setData(['success' => true] + $this->connection->status());
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to read or change the Ergonode connection.'),
            ]);
        }
    }
}
