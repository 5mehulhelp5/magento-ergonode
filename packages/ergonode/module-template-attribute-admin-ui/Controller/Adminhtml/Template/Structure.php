<?php

declare(strict_types=1);

namespace Ergonode\TemplateAttributeAdminUi\Controller\Adminhtml\Template;

use Ergonode\TemplateAttribute\Api\StructureProviderInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Structure extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_mapping';

    public function __construct(Context $context, private readonly StructureProviderInterface $structureProvider)
    {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            return $result->setData(['success' => true, 'structure' => $this->structureProvider->get(
                (string)$this->getRequest()->getParam('template_code', ''),
                (int)$this->getRequest()->getParam('attribute_set_id', 0)
            )]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        }
    }
}
