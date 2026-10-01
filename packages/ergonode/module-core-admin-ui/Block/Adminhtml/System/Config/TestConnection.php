<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

abstract class TestConnection extends Field
{
    protected $_template = 'Ergonode_CoreAdminUi::system/config/test-connection.phtml';

    public function render(AbstractElement $element)
    {
        $element = clone $element;
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $originalData = $element->getOriginalData();
        $path = explode('/', (string)$originalData['path']);
        $this->addData([
            'button_label' => __('Test connection'),
            'html_id' => $element->getHtmlId(),
            'environment' => $path[1],
            'field_prefix' => str_replace('/', '_', (string)$originalData['path']),
        ]);

        return $this->_toHtml();
    }

    public function getMageInitJson(): string
    {
        return json_encode([
            'Ergonode_CoreAdminUi/js/test-connection' => [
                'url' => $this->_urlBuilder->getUrl('ergonode/connection/testConnection'),
                'elementId' => $this->getHtmlId(),
                'mode' => $this->getModeCode(),
                'environment' => $this->getData('environment'),
                'urlFieldId' => 'ergonode_connection_' . $this->getData('environment') . '_url',
                'apiKeyFieldId' => $this->getData('field_prefix') . '_api_key',
            ],
        ], JSON_THROW_ON_ERROR);
    }

    abstract protected function getModeCode(): string;
}
