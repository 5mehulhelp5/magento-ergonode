<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Model;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeManagementProviderInterface;
use Magento\Framework\Exception\LocalizedException;

class CategoryOptionContextResolver
{
    public function __construct(
        private readonly CategoryAttributeManagementProviderInterface $managementProvider
    ) {
    }

    public function getAttributeCode(int $mappingId): string
    {
        $mapping = $this->managementProvider->getAttributeMappingRow($mappingId);
        $attributeCode = trim((string)($mapping['ergonode_attribute_code'] ?? ''));
        if (!$mapping || $attributeCode === '') {
            throw new LocalizedException(__('Options require a saved category attribute mapping.'));
        }

        return $attributeCode;
    }
}
