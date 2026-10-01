<?php

declare(strict_types=1);

namespace Ergonode\ProductAdminUi\Block\Adminhtml\System\Config;

use Ergonode\Product\Api\ProductIdentityModeProviderInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

class AssignedSkuReadiness extends Field
{
    public function __construct(
        Context $context,
        private readonly ProductIdentityModeProviderInterface $identityModeProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        try {
            $this->identityModeProvider->assertModeAvailable(ProductIdentityModeProviderInterface::MODE_ASSIGNED);
            $message = (string)__(
                'Local mapping complete. Ergonode settings, template placement '
                . 'and product value are checked during publication.'
            );
        } catch (LocalizedException $exception) {
            $message = (string)__('Incomplete: %1', $exception->getMessage());
        }

        return '<span>' . $this->escapeHtml($message) . '</span>';
    }
}
