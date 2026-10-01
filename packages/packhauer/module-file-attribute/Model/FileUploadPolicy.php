<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Model;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Driver\File\Mime;
use PackHauer\FileAttribute\Api\FileUploadPolicyInterface;

class FileUploadPolicy implements FileUploadPolicyInterface
{
    public const int MAX_FILE_SIZE = 25 * 1024 * 1024;

    private const array ALLOWED_EXTENSIONS = [
        'csv', 'doc', 'docx', 'odt', 'ods', 'odp', 'pdf', 'ppt', 'pptx', 'txt', 'xls', 'xlsx', 'zip',
    ];

    private const array ALLOWED_MIME_TYPES = [
        'application/CDFV2',
        'application/msword',
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.ms-office',
        'application/vnd.ms-powerpoint',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/x-ole-storage',
        'application/zip',
        'text/csv',
        'text/plain',
    ];

    public function __construct(
        private readonly File $fileDriver,
        private readonly Mime $mime
    ) {
    }

    public function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    public function validateUploadedFile(string $temporaryFilePath): void
    {
        try {
            $stat = $this->fileDriver->stat($temporaryFilePath);
            $size = (int)($stat['size'] ?? 0);
            $mimeType = $this->mime->getMimeType($temporaryFilePath);
        } catch (FileSystemException $exception) {
            throw new LocalizedException(__('The uploaded file could not be inspected.'), $exception);
        }

        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            throw new LocalizedException(__('The file must be non-empty and must not exceed 25 MB.'));
        }
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new LocalizedException(__('The uploaded file content type is not supported.'));
        }
    }
}
