<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Fieldset;

class OpenFieldset extends Fieldset
{
    protected function _getHeaderHtml($element)
    {
        $html = $element->getIsNested()
            ? '<tr class="nested"><td colspan="4">'
            : '<div class="ergonode-config-group">';
        if ((string)$element->getLegend() !== '') {
            $html .= $this->_getHeaderTitleHtml($element);
        }
        $html .= '<fieldset class="config" id="' . $this->escapeHtmlAttr($element->getHtmlId()) . '">';
        $html .= '<legend>' . $this->escapeHtml($element->getLegend()) . '</legend>';
        $html .= $this->_getHeaderCommentHtml($element);
        $html .= '<table cellspacing="0" class="form-list"><colgroup class="label" /><colgroup class="value" />';
        if ($this->getRequest()->getParam('website') || $this->getRequest()->getParam('store')) {
            $html .= '<colgroup class="use-default" />';
        }

        return $html . '<colgroup class="scope-label" /><colgroup /><tbody>';
    }

    protected function _getHeaderTitleHtml($element)
    {
        return '<strong class="ergonode-config-title open" id="'
            . $this->escapeHtmlAttr($element->getHtmlId()) . '-head">'
            . $this->escapeHtml($element->getLegend()) . '</strong>';
    }

    protected function _getExtraJs($element)
    {
        return '';
    }
}
