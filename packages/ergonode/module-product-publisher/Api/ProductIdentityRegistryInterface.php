<?php

declare(strict_types=1);

namespace Ergonode\ProductPublisher\Api;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Magento\Framework\Exception\LocalizedException;

interface ProductIdentityRegistryInterface
{
    /**
     * Return complete identities indexed by Magento product ID.
     *
     * @param int[] $productIds
     * @return array<int, ProductIdentityInterface>
     */
    public function getIdentitiesByProductIds(array $productIds): array;

    /**
     * @param string[] $skus
     * @return ProductIdentityInterface[]
     */
    public function getIdentitiesByErgonodeSkus(array $skus): array;

    /**
     * Read configured Magento identity attribute values for products.
     *
     * @param int[] $productIds
     * @return array<int, string>
     */
    public function getMappedSkuValuesByProductIds(array $productIds): array;

    /**
     * @param string[] $skus
     * @return array<string, int>
     */
    public function findMappedSkuProductIds(array $skus): array;

    /**
     * Return the configured Magento identity attribute code.
     *
     * @return string
     */
    public function getMappedSkuAttributeCode(): string;

    /**
     * Validate immutable identities with one batch lookup.
     *
     * @param array<int, string> $productSkus Magento product ID to current SKU.
     * @return array<int, string> Validation messages indexed by Magento product ID.
     * @throws LocalizedException
     */
    public function getStabilityFailures(array $productSkus): array;

    /**
     * Assert that previously published products still use their immutable Ergonode SKU.
     *
     * @param array<int, string> $productSkus Magento product ID to current SKU.
     * @return void
     * @throws LocalizedException
     */
    public function assertStable(array $productSkus): void;

    /**
     * Persist successful Magento product ID to Ergonode SKU mappings.
     *
     * @param array<int, string> $productSkus Magento product ID to published SKU.
     * @return void
     * @throws LocalizedException
     */
    public function recordPublished(array $productSkus): void;

    /**
     * Persist Ergonode-assigned native SKUs after creation or fallback lookup.
     *
     * @param array<int, string> $productSkus Magento product ID to native Ergonode SKU.
     * @return void
     * @throws LocalizedException
     */
    public function bindAssignedBatch(array $productSkus): void;

    /**
     * Persist Magento attribute-backed native SKUs after successful publication.
     *
     * @param array<int, string> $productSkus Magento product ID to native Ergonode SKU.
     * @return void
     * @throws LocalizedException
     */
    public function bindMappedBatch(array $productSkus): void;
}
