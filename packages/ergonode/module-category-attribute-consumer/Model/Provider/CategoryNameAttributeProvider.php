<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Provider;

use Magento\Catalog\Model\ResourceModel\Category\Attribute\CollectionFactory;

class CategoryNameAttributeProvider
{
    /** @var array<string, string>|null */
    private ?array $attributes = null;

    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /** @return array<string, string> */
    public function getAttributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }
        $attributes = [];
        foreach ($this->collectionFactory->create() as $attribute) {
            $code = (string)$attribute->getAttributeCode();
            if ($attribute->getFrontendInput() !== 'text' || $attribute->getBackendType() !== 'varchar'
                || $attribute->getSourceModel() || $code === 'url_key'
                || (!$attribute->getIsUserDefined() && !in_array($code, ['name', 'meta_title'], true))
            ) {
                continue;
            }
            $attributes[$code] = (string)($attribute->getDefaultFrontendLabel() ?: $code);
        }
        asort($attributes);

        return $this->attributes = $attributes;
    }
}
