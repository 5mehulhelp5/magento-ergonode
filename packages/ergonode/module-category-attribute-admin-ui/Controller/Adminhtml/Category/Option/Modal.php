<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeAdminUi\Controller\Adminhtml\Category\Option;

use Ergonode\CategoryAttributeAdminUi\Block\Adminhtml\CategoryOption\Mapping;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\View\LayoutInterface;

class Modal extends Action implements HttpGetActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_mapping';

    public function __construct(
        Context $context,
        private readonly JsonSerializer $json,
        private readonly LayoutInterface $layout
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $mappingId = (int)$this->getRequest()->getParam('mapping_id');

        if ($mappingId <= 0) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Nieprawidłowa para atrybutów kategorii.'),
            ]);
        }

        $block = $this->layout->createBlock(
            Mapping::class,
            'ergonode.category.option.mapping.modal',
            ['data' => [
                'template' => 'Ergonode_CoreAdminUi::option/mapping.phtml',
                'embedded' => true,
            ]]
        );
        $context = $block->getAttributeContext();

        if ((int)($context['mapping_id'] ?? 0) !== $mappingId) {
            return $result->setData([
                'success' => false,
                'message' => (string)__('Nie znaleziono wybranej pary atrybutów kategorii.'),
            ]);
        }

        return $result->setData([
            'success' => true,
            'html' => $block->toHtml(),
            'config' => $this->json->unserialize($block->getMappingConfigJson()),
        ]);
    }
}
