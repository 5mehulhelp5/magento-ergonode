<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Model\Config\Source;

use Ergonode\CategoryAttributeConsumer\Model\Provider\CategoryNameAttributeProvider;
use Magento\Framework\Data\OptionSourceInterface;

class CategoryNameAttribute implements OptionSourceInterface
{
    public function __construct(private readonly CategoryNameAttributeProvider $attributeProvider)
    {
    }

    /** @return list<array{value: string, label: string|\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('Choose a field')]];
        foreach ($this->attributeProvider->getAttributes() as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }
}
