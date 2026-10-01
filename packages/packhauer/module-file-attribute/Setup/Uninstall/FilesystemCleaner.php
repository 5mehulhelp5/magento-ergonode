<?php

declare(strict_types=1);

namespace PackHauer\FileAttribute\Setup\Uninstall;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PackHauer\FileAttribute\Model\FileStorage;

class FilesystemCleaner
{
    private readonly WriteInterface $mediaDirectory;

    public function __construct(Filesystem $filesystem)
    {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function execute(AttributeCleanup $cleanup): void
    {
        $preservedPaths = array_fill_keys($cleanup->preservedPaths, true);
        foreach ($cleanup->attributeCodes as $attributeCode) {
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $attributeCode) !== 1) {
                continue;
            }

            $this->cleanDirectory(FileStorage::PERMANENT_DIRECTORY . '/' . $attributeCode, $preservedPaths);
        }

        $this->mediaDirectory->delete(FileStorage::TEMPORARY_DIRECTORY);
    }

    /** @param array<string, true> $preservedPaths */
    private function cleanDirectory(string $directory, array $preservedPaths): void
    {
        if (!$this->mediaDirectory->isDirectory($directory)) {
            return;
        }

        foreach ($this->mediaDirectory->read($directory) as $path) {
            if ($this->mediaDirectory->isDirectory($path)) {
                $this->cleanDirectory($path, $preservedPaths);
                continue;
            }
            if (!isset($preservedPaths[$path])) {
                $this->mediaDirectory->delete($path);
            }
        }

        if ($this->mediaDirectory->read($directory) === []) {
            $this->mediaDirectory->delete($directory);
        }
    }
}
