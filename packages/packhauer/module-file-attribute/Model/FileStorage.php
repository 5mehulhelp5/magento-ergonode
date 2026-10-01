<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PackHauer\FileAttribute\Api\FileStorageInterface;

class FileStorage implements FileStorageInterface
{
    public const string TEMPORARY_DIRECTORY = 'catalog/product/tmp/files';
    public const string PERMANENT_DIRECTORY = 'catalog/product/files';

    private readonly WriteInterface $mediaDirectory;

    public function __construct(Filesystem $filesystem)
    {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function getTemporaryDirectory(): string
    {
        return self::TEMPORARY_DIRECTORY;
    }

    public function getPermanentDirectory(string $attributeCode): string
    {
        $attributeCode = trim($attributeCode);
        if ($attributeCode === '' || preg_match('/^[a-zA-Z0-9_-]+$/', $attributeCode) !== 1) {
            throw new LocalizedException(__('Invalid file attribute code "%1".', $attributeCode));
        }

        return self::PERMANENT_DIRECTORY . '/' . $attributeCode;
    }

    public function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === ''
            || str_starts_with($path, '/')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || str_contains($path, '://')
            || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1
        ) {
            throw new LocalizedException(__('File attribute path must be relative to the media directory.'));
        }

        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    public function promoteTemporaryFile(string $temporaryPath, string $attributeCode): string
    {
        $temporaryPath = $this->normalizePath($temporaryPath);
        $prefix = self::TEMPORARY_DIRECTORY . '/';
        if (!str_starts_with($temporaryPath, $prefix)) {
            throw new LocalizedException(__('The uploaded file is outside the temporary file attribute directory.'));
        }
        if (!$this->mediaDirectory->isFile($temporaryPath)) {
            throw new LocalizedException(__('The uploaded file no longer exists in temporary storage.'));
        }

        $relativePath = substr($temporaryPath, strlen($prefix));
        $targetPath = $this->availablePath(
            $this->getPermanentDirectory($attributeCode) . '/' . $relativePath
        );

        try {
            [$targetDirectory] = $this->splitPath($targetPath);
            $this->mediaDirectory->create($targetDirectory);
            $this->mediaDirectory->renameFile($temporaryPath, $targetPath);
        } catch (FileSystemException $exception) {
            throw new LocalizedException(
                __('The uploaded file could not be moved into permanent storage.'),
                $exception
            );
        }

        return $targetPath;
    }

    private function availablePath(string $path): string
    {
        if (!$this->mediaDirectory->isExist($path)) {
            return $path;
        }

        [$directory, $filename, $extension] = $this->splitPath($path);
        for ($suffix = 1; $suffix <= 1000; $suffix++) {
            $candidate = $directory . '/' . $filename . '_' . $suffix
                . ($extension === '' ? '' : '.' . $extension);
            if (!$this->mediaDirectory->isExist($candidate)) {
                return $candidate;
            }
        }

        throw new LocalizedException(__('A unique permanent path for the uploaded file could not be created.'));
    }

    /** @return array{string, string, string} */
    private function splitPath(string $path): array
    {
        $separatorPosition = strrpos($path, '/');
        $directory = $separatorPosition === false ? '' : substr($path, 0, $separatorPosition);
        $basename = $separatorPosition === false ? $path : substr($path, $separatorPosition + 1);
        $extensionPosition = strrpos($basename, '.');
        if ($extensionPosition === false || $extensionPosition === 0) {
            return [$directory, $basename, ''];
        }

        return [
            $directory,
            substr($basename, 0, $extensionPosition),
            substr($basename, $extensionPosition + 1),
        ];
    }
}
