<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Model\Config\Source;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributeCode implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductAttributePolicy $attributePolicy
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('backend_type', ['neq' => 'static']);
        foreach ($collection as $attribute) {
            if (!$attribute instanceof Attribute) {
                continue;
            }
            $code = trim((string)$attribute->getAttributeCode());
            if ($code === '' || !$this->attributePolicy->isMappable($code)) {
                continue;
            }
            $label = trim((string)$attribute->getDefaultFrontendLabel());
            $options[$code] = [
                'value' => $code,
                'label' => ($label !== '' ? $label : $code) . ' (' . $code . ')',
            ];
        }
        uasort(
            $options,
            static fn (array $left, array $right): int => strcasecmp(
                (string)$left['label'],
                (string)$right['label']
            )
        );

        return [
            ['value' => '', 'label' => (string)__('Disabled')],
            ...array_values($options),
        ];
    }
}
