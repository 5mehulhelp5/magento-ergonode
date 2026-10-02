<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Scan extends Field
{
    private const string SCAN_RESOURCE = 'Ergonode_Media::scan';

    protected $_template = 'Ergonode_MediaAdminUi::system/config/scan.phtml';

    public function render(AbstractElement $element)
    {
        $element = clone $element;
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        if (!$this->_authorization->isAllowed(self::SCAN_RESOURCE)) {
            return $this->escapeHtml(__('You do not have permission to scan media.'));
        }
        return $this->_toHtml();
    }

    public function getMageInitJson(): string
    {
        return json_encode([
            'Ergonode_MediaAdminUi/js/media-scan' => [
                'statusUrl' => $this->_urlBuilder->getUrl('ergonode_media/scan/status'),
                'startUrl' => $this->_urlBuilder->getUrl('ergonode_media/scan/start'),
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
