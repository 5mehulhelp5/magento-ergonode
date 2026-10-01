<?php

declare(strict_types=1);

namespace Ergonode\ProductAttribute\Api;

interface MagentoAttributeMetadataContributorInterface
{
    /**
     * Return Magento product attribute codes that must be available even when they are not visible.
     *
     * @return string[]
     */
    public function getAdditionalAttributeCodes(): array;

    /**
     * Add feature-specific metadata to one Magento product attribute.
     *
     * @param  array<string, bool|string> $metadata
     * @return array<string, bool|string>
     */
    public function contribute(array $metadata): array;
}
