<?php

declare(strict_types=1);

namespace Ergonode\ProductTemplatePublisher\Model\Source;

use Ergonode\ProductPublisher\Api\ProductTemplateCodeProviderInterface;
use Ergonode\Template\Api\TemplateAttributeSetMappingProviderInterface;

class MappedProductTemplateCodeProvider implements ProductTemplateCodeProviderInterface
{
    public function __construct(
        private readonly TemplateAttributeSetMappingProviderInterface $mappingProvider
    ) {
    }

    public function getTemplateCodesByAttributeSetIds(array $attributeSetIds): array
    {
        return $this->mappingProvider->getTemplateCodesByAttributeSetIds($attributeSetIds);
    }
}
