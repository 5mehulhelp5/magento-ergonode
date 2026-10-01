<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeConsumerAdminUi\Controller\Adminhtml\Attribute;

use Ergonode\AttributeConsumer\Api\AttributeSnapshotRefreshInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_AttributeConsumer::attribute_refresh';

    public function __construct(
        Context $context,
        private readonly AttributeSnapshotRefreshInterface $snapshotRefresh
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $cursor = (string)$this->getRequest()->getParam('cursor', '');
            $pageSize = (int)$this->getRequest()->getParam('page_size', 200);
            $data = $this->snapshotRefresh->refreshSnapshot(
                $cursor !== '' ? $cursor : null,
                $pageSize ?: null
            );

            return $result->setData(['success' => true] + $data);
        } catch (LocalizedException $exception) {
            return $result->setData(
                [
                'success' => false,
                'message' => $exception->getMessage(),
                ]
            );
        } catch (Throwable $exception) {
            return $result->setData(
                [
                'success' => false,
                'message' => (string)__('Unable to refresh Ergonode attribute data: %1', $exception->getMessage()),
                ]
            );
        }
    }
}
