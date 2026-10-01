<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisherAdminUi\Controller\Adminhtml\Publication;

use Ergonode\ProductPublisherAdminUi\Model\PublicationRequest;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class Publish extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_ProductPublisher::publish';

    public function __construct(
        Context $context,
        private readonly PublicationRequest $service,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $data = $this->service->publish(
                (string)$this->getRequest()->getParam('ids', '')
            );

            return $result->setData(['success' => true, 'items' => $data]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $this->logger->error('Product publication publish request failed.', ['exception' => $exception]);

            return $result->setData([
                'success' => false,
                'message' => (string)__(
                    'Unable to process the product publication request. Check the application log.'
                ),
            ]);
        }
    }
}
