<?php

declare(strict_types=1);

namespace Ergonode\Media\Setup\Uninstall;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;

class FilesystemCleaner
{
    private const array OWNED_DIRECTORIES = [
        [DirectoryList::VAR_DIR, 'ergonode/media'],
        [DirectoryList::MEDIA, 'catalog/product/ergonode/shared'],
        [DirectoryList::MEDIA, 'catalog/product/ergonode/seo'],
    ];

    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly File $file
    ) {
    }

    public function execute(): void
    {
        foreach (self::OWNED_DIRECTORIES as [$root, $relativePath]) {
            $path = rtrim($this->directoryList->getPath($root), '/') . '/' . $relativePath;
            if (!$this->file->fileExists($path, false)) {
                continue;
            }
            if (!$this->file->rmdir($path, true)) {
                throw new LocalizedException(__('Unable to remove Ergonode media directory "%1".', $path));
            }
        }
    }
}
