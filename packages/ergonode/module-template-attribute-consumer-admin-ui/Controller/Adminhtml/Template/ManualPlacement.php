<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeConsumerAdminUi\Controller\Adminhtml\Template;

use Ergonode\TemplateAttributeConsumer\Api\ManualPlacementSaverInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class ManualPlacement extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_save';

    public function __construct(Context $context, private readonly ManualPlacementSaverInterface $saver)
    {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $manual = (string)$this->getRequest()->getParam('manual', '');
        if (!in_array($manual, ['0', '1'], true)) {
            return $result->setData(['success' => false, 'message' => (string)__('Invalid manual placement value.')]);
        }
        try {
            $this->saver->save(
                (string)$this->getRequest()->getParam('template_code', ''),
                (int)$this->getRequest()->getParam('attribute_set_id', 0),
                (int)$this->getRequest()->getParam('attribute_id', 0),
                $manual === '1'
            );
            return $result->setData(['success' => true]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        }
    }
}
