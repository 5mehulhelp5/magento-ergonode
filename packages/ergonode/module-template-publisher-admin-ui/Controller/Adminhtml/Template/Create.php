<?php

declare(strict_types=1);

namespace Ergonode\TemplatePublisherAdminUi\Controller\Adminhtml\Template;

use Ergonode\TemplatePublisherAdminUi\Model\TemplateFromAttributeSetCreator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

class Create extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_TemplateConsumer::template_save';

    public function __construct(
        Context $context,
        private readonly TemplateFromAttributeSetCreator $templateCreator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $templateCode = trim((string)$this->getRequest()->getParam('template_code', ''));
        $attributeSetId = (int)$this->getRequest()->getParam('attribute_set_id', 0);

        try {
            $template = $this->templateCreator->create($templateCode, $attributeSetId);

            return $result->setData([
                'success' => true,
                'message' => (string)__('Ergonode template "%1" has been created.', $template['code']),
                'template' => $template,
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unable to create an Ergonode template from a Magento attribute set.', [
                'template_code' => $templateCode,
                'attribute_set_id' => $attributeSetId,
                'exception' => $exception,
            ]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to create the Ergonode template.'),
            ]);
        }
    }
}
