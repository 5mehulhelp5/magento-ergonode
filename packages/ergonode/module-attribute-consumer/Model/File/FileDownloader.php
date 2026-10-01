<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Model\File;

use Ergonode\AttributeConsumer\Api\ErgonodeFileDownloaderInterface;
use Ergonode\Core\Model\Config\ConfigProvider;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class FileDownloader implements ErgonodeFileDownloaderInterface
{
    private const string MEDIA_DIRECTORY = 'ergonode/file';
    private const string ERGONODE_FILE_PATH = 'api/multimedia/file';
    private const array BLOCKED_EXTENSIONS = [
        'asp',
        'aspx',
        'cgi',
        'htm',
        'html',
        'jsp',
        'phtml',
        'phar',
        'php',
        'pl',
        'py',
        'sh',
    ];

    public function __construct(
        private readonly RemoteFileDownloader $downloader,
        private readonly File $file,
        private readonly DirectoryList $directoryList,
        private readonly ConfigProvider $configProvider,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array{relative_path: string, media_url: string, source_url: string, downloaded: bool}
     * @throws LocalizedException
     */
    public function download(string $source, string $mediaDirectory = self::MEDIA_DIRECTORY): array
    {
        $source = trim($source);
        if ($source === '') {
            throw new LocalizedException(__('Missing Ergonode file source.'));
        }

        $relativePath = $this->sourceToRelativePath($source, $mediaDirectory);
        $absolutePath = $this->getAbsolutePath($relativePath);
        $downloaded = false;

        try {
            if (!$this->file->fileExists($absolutePath)) {
                $this->file->checkAndCreateFolder($this->file->dirname($absolutePath));
                $this->downloadFile($source, $absolutePath);
                $downloaded = true;
            }
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new LocalizedException(
                __('Failed to download Ergonode file "%1": %2', $source, $exception->getMessage())
            );
        }

        return [
            'relative_path' => $relativePath,
            'media_url' => $this->getMediaUrl($relativePath),
            'source_url' => $this->resolveSourceUrl($source),
            'downloaded' => $downloaded,
        ];
    }

    /**
     * @throws LocalizedException
     */
    public function sourceToRelativePath(string $source, string $mediaDirectory = self::MEDIA_DIRECTORY): string
    {
        $source = trim($source);
        $mediaDirectory = $this->sanitizeMediaDirectory($mediaDirectory);
        $mediaBaseUrl = $this->getMediaBaseUrl();

        if ($mediaBaseUrl !== '' && str_starts_with($source, $mediaBaseUrl)) {
            $source = substr($source, strlen($mediaBaseUrl));
        }

        if (str_starts_with($source, $mediaDirectory . '/')) {
            return $this->sanitizeRelativePath($source);
        }

        $fileName = $this->resolveFileName($source);
        $prefix = substr($fileName, 0, 2);
        $parts = [$mediaDirectory];

        if (preg_match('/^[a-zA-Z0-9]{2}$/', $prefix)) {
            $parts[] = $prefix[0];
            $parts[] = $prefix[1];
        }

        $parts[] = $fileName;

        return implode('/', $parts);
    }

    private function sanitizeMediaDirectory(string $directory): string
    {
        $directory = str_replace('\\', '/', trim($directory, '/'));
        if ($directory === ''
            || str_contains($directory, '..')
            || !preg_match('#^[a-zA-Z0-9/_-]+$#', $directory)
        ) {
            throw new LocalizedException(__('Invalid Ergonode media directory "%1".', $directory));
        }

        return $directory;
    }

    private function downloadFile(string $source, string $absolutePath): void
    {
        $temporary = tempnam($this->file->dirname($absolutePath), '.ergonode-');
        if ($temporary === false) {
            throw new LocalizedException(__('Unable to create a temporary download file.'));
        }
        try {
            $this->downloader->download($this->resolveSourceUrl($source), $temporary);
            if (!chmod($temporary, 0666 & ~umask()) || !$this->file->mv($temporary, $absolutePath)) {
                throw new LocalizedException(__('Unable to publish the downloaded Ergonode file.'));
            }
        } finally {
            if ($this->file->fileExists($temporary)) {
                $this->file->rm($temporary);
            }
        }
    }

    private function resolveSourceUrl(string $source): string
    {
        if (preg_match('#^https?://#i', $source)) {
            return $source;
        }

        return rtrim($this->resolveErgonodeBaseUrl(), '/') . '/'
            . self::ERGONODE_FILE_PATH . '/'
            . rawurlencode($this->resolveFileName($source));
    }

    private function resolveErgonodeBaseUrl(): string
    {
        $url = trim($this->configProvider->getGraphQlUrl());
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($url, '/');
        }

        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . (string)$parts['port'];
        }

        return $base;
    }

    /**
     * @throws LocalizedException
     */
    private function resolveFileName(string $source): string
    {
        $path = (string)(parse_url($source, PHP_URL_PATH) ?: $source);
        $fileName = basename(trim($path, '/'));
        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName) ?: '';
        $fileName = trim($fileName, '._-');

        if ($fileName === '') {
            throw new LocalizedException(__('Unable to resolve Ergonode file name from "%1".', $source));
        }

        $pathInfo = $this->file->getPathInfo($fileName);
        $extension = strtolower((string)($pathInfo['extension'] ?? ''));
        if ($extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new LocalizedException(__('Ergonode file extension "%1" is not allowed.', $extension));
        }

        return $fileName;
    }

    /**
     * @throws LocalizedException
     */
    private function sanitizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path, '/'));
        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\')) {
            throw new LocalizedException(__('Invalid Ergonode file path "%1".', $path));
        }

        return $path;
    }

    private function getAbsolutePath(string $relativePath): string
    {
        return rtrim($this->directoryList->getPath(DirectoryList::MEDIA), '/')
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
    }

    private function getMediaUrl(string $relativePath): string
    {
        return $this->getMediaBaseUrl() . ltrim($relativePath, '/');
    }

    private function getMediaBaseUrl(): string
    {
        return rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/';
    }
}
