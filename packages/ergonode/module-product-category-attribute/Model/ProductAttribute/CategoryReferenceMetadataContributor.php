<?php

declare(strict_types=1);

namespace Ergonode\ProductCategoryAttribute\Model\ProductAttribute;

use Ergonode\ProductAttribute\Api\MagentoAttributeMetadataContributorInterface;
use Ergonode\ProductCategoryAttribute\Api\CategoryReferenceAttributeConfigInterface;

class CategoryReferenceMetadataContributor implements MagentoAttributeMetadataContributorInterface
{
    public function __construct(private readonly CategoryReferenceAttributeConfigInterface $config)
    {
    }

    public function getAdditionalAttributeCodes(): array
    {
        $code = $this->config->getAttributeCode();

        return $code === '' ? [] : [$code];
    }

    public function contribute(array $metadata): array
    {
        $metadata['category_reference'] = $this->config->isConfigured((string)($metadata['code'] ?? ''));

        return $metadata;
    }
}
