<?php

declare(strict_types=1);

namespace Ergonode\MediaAdminUi\Block\Adminhtml\System\Config;

use Ergonode\Media\Api\ImageAttributeOptionsInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Throwable;

class AdditionalImages extends Field
{
    protected $_template = 'Ergonode_MediaAdminUi::system/config/additional-images.phtml';
    private string $inputName = '';
    /** @var list<array{attribute:string,position:int}> */
    private array $rows = [];
    public function __construct(
        Context $context,
        private readonly ImageAttributeOptionsInterface $attributes,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->inputName = (string)$element->getName();
        $this->rows = array_values((array)$element->getValue());
        return $this->_toHtml();
    }
    public function getMageInitJson(): string
    {
        $error = null;
        try {
            $options = $this->attributes->getOptions();
        } catch (Throwable) {
            $options = [];
            $error = (string)__('Image attributes are unavailable. Check the Ergonode connection.');
        }
        return json_encode(['Ergonode_MediaAdminUi/js/additional-images' => [
            'name' => $this->inputName, 'rows' => $this->rows, 'options' => $options, 'error' => $error,
        ]], JSON_THROW_ON_ERROR);
    }
}
