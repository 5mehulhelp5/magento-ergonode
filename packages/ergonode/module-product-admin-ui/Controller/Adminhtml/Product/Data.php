<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Controller\Adminhtml\Product;

use Ergonode\ProductAdminUi\Model\GridData;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class Data extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Product::products';

    public function __construct(
        Context $context,
        private readonly GridData $service,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $data = $this->service->getPage(
                (string)$this->getRequest()->getParam('search', ''),
                (int)$this->getRequest()->getParam('page', 1),
                (int)$this->getRequest()->getParam('page_size', 20),
                (array)$this->getRequest()->getParam('criteria', [])
            );

            return $result->setData(['success' => true, 'data' => $data]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->logger->error('Product workspace data request failed.', ['exception' => $exception]);

            return $result->setData([
                'success' => false,
                'message' => (string)__(
                    'Unable to load the product list. Check the application log.'
                ),
            ]);
        }
    }
}
