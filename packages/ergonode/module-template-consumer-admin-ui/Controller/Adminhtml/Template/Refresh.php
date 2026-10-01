<?php

declare(strict_types=1);

namespace Ergonode\TemplateConsumerAdminUi\Controller\Adminhtml\Template;

use Ergonode\TemplateConsumer\Api\TemplateSnapshotRefresherInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Throwable;

class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_refresh';

    public function __construct(
        Context $context,
        private readonly TemplateSnapshotRefresherInterface $templateSnapshotRefresher
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $data = $this->templateSnapshotRefresher->refresh();

            return $result->setData(['success' => true] + $data);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to refresh Ergonode templates: %1', $exception->getMessage()),
            ]);
        }
    }
}
