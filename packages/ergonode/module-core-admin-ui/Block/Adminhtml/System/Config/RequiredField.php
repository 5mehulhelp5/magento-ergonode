<?php

declare(strict_types=1);

namespace Ergonode\CoreAdminUi\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class RequiredField extends Field
{
    /**
     * @param AbstractElement $element
     * @param string $html
     * @return string
     */
    protected function _decorateRowHtml($element, $html)
    {
        return '<tr id="row_' . $element->getHtmlId() . '" class="required _required">' . $html . '</tr>';
    }
}
