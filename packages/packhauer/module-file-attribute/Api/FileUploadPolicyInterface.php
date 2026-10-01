<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Api;

use Magento\Framework\Exception\LocalizedException;

interface FileUploadPolicyInterface
{
    /**
     * @return string[]
     */
    public function getAllowedExtensions(): array;

    /**
     * @return int
     */
    public function getMaxFileSize(): int;

    /**
     * Validate the content and size of an uploaded temporary file.
     *
     * @param string $temporaryFilePath
     * @return void
     * @throws LocalizedException
     */
    public function validateUploadedFile(string $temporaryFilePath): void;
}
