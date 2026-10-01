<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumerAdminUi\Model\Mapping;

use Ergonode\CategoryAttributeAdminUi\Api\SourceMetadataProviderInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeManagementProviderInterface;

class SourceMetadata implements SourceMetadataProviderInterface
{
    public function __construct(private readonly CategoryAttributeManagementProviderInterface $provider)
    {
    }

    public function getAttributes(): array
    {
        return $this->provider->getErgonodeAttributes();
    }

    public function getOptions(string $attributeCode): array
    {
        return $this->provider->getErgonodeOptions($attributeCode);
    }
}
