<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Model\Provider;

use Ergonode\ProductAttribute\Api\MagentoAttributeMetadataContributorInterface;

class MagentoAttributeMetadataContributorPool
{
    /**
     * @param MagentoAttributeMetadataContributorInterface[] $contributors
     */
    public function __construct(private readonly array $contributors = [])
    {
    }

    /**
     * @return string[]
     */
    public function getAdditionalAttributeCodes(): array
    {
        $codes = [];
        foreach ($this->contributors as $contributor) {
            $codes = [...$codes, ...$contributor->getAdditionalAttributeCodes()];
        }

        return array_values(array_unique(array_filter(array_map('trim', $codes))));
    }

    /**
     * @param  array<string, bool|string> $metadata
     * @return array<string, bool|string>
     */
    public function contribute(array $metadata): array
    {
        foreach ($this->contributors as $contributor) {
            $metadata = $contributor->contribute($metadata);
        }

        return $metadata;
    }
}
