<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Controller\Adminhtml\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;
use Ergonode\CoreAdminUi\Model\Connection\ConnectionTester;
use Ergonode\CoreAdminUi\Model\Connection\CredentialResolver;

class TestConnection extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Core::config';

    public function __construct(
        Context $context,
        private readonly CredentialResolver $credentialResolver,
        private readonly ConnectionTester $connectionTester
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $mode = trim((string)$this->getRequest()->getParam('mode'));
            $environment = trim((string)$this->getRequest()->getParam('environment'));
            $apiKey = $this->credentialResolver->resolveApiKey(
                $mode,
                $environment,
                (string)$this->getRequest()->getParam('api_key')
            );
            $this->connectionTester->test(
                (string)$this->getRequest()->getParam('ergonode_url'),
                $apiKey,
                $environment
            );

            return $result->setData(['success' => true]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to test the Ergonode connection.'),
            ]);
        }
    }
}
