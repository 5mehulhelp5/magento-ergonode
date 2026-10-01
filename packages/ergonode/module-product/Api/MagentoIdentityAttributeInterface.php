<?php

declare(strict_types=1);

namespace Ergonode\Product\Api;

interface MagentoIdentityAttributeInterface
{
    /** @return array<string, string> Eligible attribute codes and labels. */
    public function getEligibleAttributes(): array;

    /**
     * Return the configured Magento product attribute code.
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Validate that the selected attribute can hold an immutable Ergonode SKU.
     *
     * @return void
     */
    public function validate(): void;

    /**
     * Validate a proposed attribute code before configuration is saved.
     *
     * @param string $code
     * @return void
     */
    public function validateCode(string $code): void;

    /**
     * Read values by Magento product ID.
     *
     * @param int[] $productIds
     * @return array<int, string>
     */
    public function getValuesByProductIds(array $productIds): array;

    /**
     * Find a unique Magento product by its stored Ergonode SKU.
     *
     * @param string $ergonodeSku
     * @return int|null
     */
    public function findProductId(string $ergonodeSku): ?int;

    /**
     * Find unique Magento owners for a selected set of native SKUs.
     *
     * @param string[] $ergonodeSkus
     * @return array<string, int>
     */
    public function findProductIds(array $ergonodeSkus): array;

    /**
     * Persist the native SKU without changing a different existing value.
     *
     * @param int $productId
     * @param string $ergonodeSku
     * @return void
     */
    public function write(int $productId, string $ergonodeSku): void;
}
