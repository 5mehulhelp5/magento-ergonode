<?php

declare(strict_types=1);

namespace Ergonode\PublisherAdminUi\Controller\Adminhtml\Rest;

use Ergonode\Publisher\Api\Rest\ConnectionManagementInterface;
use Ergonode\PublisherAdminUi\Model\Rest\ConnectionStorage;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Login extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Publisher::rest_connection';

    public function __construct(
        Context $context,
        private readonly ConnectionManagementInterface $authenticator,
        private readonly ConnectionStorage $storage
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $this->storage->selectPersistence(
                (string)$this->getRequest()->getParam('remember_me', '0') === '1'
            );
            $this->authenticator->login(
                (string)$this->getRequest()->getParam('username', ''),
                (string)$this->getRequest()->getParam('password', '')
            );

            return $result->setData(['success' => true] + $this->authenticator->status());
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'authenticated' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'authenticated' => false,
                'message' => (string)__('Unable to log in to Ergonode.'),
            ]);
        }
    }
}
