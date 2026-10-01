<?php

declare(strict_types=1);

namespace Ergonode\Product\Api;

use Ergonode\Product\Api\Data\ProductIdentityInterface;
use Magento\Framework\Exception\LocalizedException;

interface ProductIdentityServiceInterface
{
    /**
     * Return complete identities indexed by Magento product ID.
     *
     * @param int[] $productIds
     * @return array<int, ProductIdentityInterface>
     */
    public function getIdentitiesByProductIds(array $productIds): array;

    /**
     * Return complete identities matching native Ergonode SKUs.
     *
     * @param string[] $ergonodeSkus
     * @return ProductIdentityInterface[]
     */
    public function getIdentitiesByErgonodeSkus(array $ergonodeSkus): array;

    /**
     * Resolve immutable Ergonode SKUs by Magento product ID.
     *
     * @param int[] $productIds
     * @return array<int, string>
     */
    public function getErgonodeSkusByProductIds(array $productIds): array;

    /**
     * Bind a Magento product to one immutable native Ergonode SKU.
     *
     * @param int $productId
     * @param string $ergonodeSku
     * @param string $identityMode
     * @return void
     * @throws LocalizedException
     */
    public function bind(int $productId, string $ergonodeSku, string $identityMode): void;

    /**
     * Bind multiple Magento products to immutable native Ergonode SKUs in one transaction.
     *
     * @param array<int, array{ergonode_sku: string, identity_mode: string}> $identities
     * @return void
     * @throws LocalizedException
     */
    public function bindMany(array $identities): void;

    /**
     * Return the last successfully imported payload hash for a product.
     *
     * @param int $productId
     * @return string|null
     */
    public function getImportHash(int $productId): ?string;

    /**
     * Persist a successful Ergonode-to-Magento synchronization.
     *
     * @param int $productId
     * @param string $ergonodeSku
     * @param string $contentHash
     * @return void
     * @throws LocalizedException
     */
    public function recordImported(int $productId, string $ergonodeSku, string $contentHash): void;

    /**
     * Clear the imported payload marker while retaining immutable identity ownership.
     *
     * @param string $ergonodeSku
     * @return void
     */
    public function clearImportHash(string $ergonodeSku): void;
}
