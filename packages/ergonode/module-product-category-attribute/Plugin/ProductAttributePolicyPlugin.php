<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Plugin;

use Ergonode\ProductAttribute\Model\Config\ProductAttributePolicy;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;

class ProductAttributePolicyPlugin
{
    public function __construct(private readonly CategoryReferenceAttributeConfigInterface $config)
    {
    }

    /** @param string[]|null $result @return string[]|null */
    public function afterGetAllowedErgonodeTypes(
        ProductAttributePolicy $subject,
        ?array $result,
        string $attributeCode
    ): ?array {
        unset($subject);

        return $this->config->isConfigured($attributeCode) ? ['text'] : $result;
    }

    /** @param array<string, string[]> $result @return array<string, string[]> */
    public function afterGetErgonodeTypeConstraints(ProductAttributePolicy $subject, array $result): array
    {
        unset($subject);
        $code = $this->config->getAttributeCode();
        if ($code !== '') {
            $result[$code] = ['text'];
        }

        return $result;
    }
}
