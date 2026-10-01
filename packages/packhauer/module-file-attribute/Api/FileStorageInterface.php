<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Api;

use Magento\Framework\Exception\LocalizedException;

interface FileStorageInterface
{
    /**
     * @return string
     */
    public function getTemporaryDirectory(): string;

    /**
     * Return the permanent media directory owned by a product file attribute.
     *
     * @param string $attributeCode
     * @return string
     * @throws LocalizedException
     */
    public function getPermanentDirectory(string $attributeCode): string;

    /**
     * Validate and normalize a path relative to Magento's media directory.
     *
     * @param string $path
     * @return string
     * @throws LocalizedException
     */
    public function normalizePath(string $path): string;

    /**
     * Move a file from the module's temporary directory into an attribute-owned media directory.
     *
     * @param string $temporaryPath
     * @param string $attributeCode
     * @return string
     * @throws LocalizedException
     */
    public function promoteTemporaryFile(string $temporaryPath, string $attributeCode): string;
}
