<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use FilesystemIterator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LocalFiles
{
    private const string ROOT = 'catalog/product/';

    public function __construct(
        private readonly DirectoryList $directories,
        private readonly File $driver
    ) {
    }

    /** @return iterable<string> */
    public function paths(): iterable
    {
        $root = $this->mediaRoot() . '/' . self::ROOT;
        if (!$this->driver->isDirectory($root)) {
            return;
        }
        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directory, static fn (SplFileInfo $file): bool =>
            !$file->isLink() && !str_starts_with($file->getFilename(), '.')
            && !in_array($file->getFilename(), ['cache', 'tmp', 'archive'], true));
        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($file->isFile()) {
                yield self::ROOT . substr($file->getPathname(), strlen($root));
            }
        }
    }

    /** @return array{size:int,modified_at:int}|null */
    public function stat(string $path): ?array
    {
        $absolute = $this->absolute($path);
        clearstatcache(true, $absolute);
        if (!$this->driver->isFile($absolute)) {
            return null;
        }
        $stat = stat($absolute);
        if ($stat === false) {
            throw new LocalizedException(__('Unable to inspect local media file "%1".', $path));
        }

        return ['size' => (int)$stat['size'], 'modified_at' => (int)$stat['mtime']];
    }

    public function hash(string $path): string
    {
        $hash = hash_file('sha256', $this->absolute($path), true);
        if ($hash === false) {
            throw new LocalizedException(__('Unable to hash local media file "%1".', $path));
        }

        return $hash;
    }

    public function absolute(string $path): string
    {
        if (!str_starts_with($path, self::ROOT) || str_contains($path, '..') || str_contains($path, '\\')
            || str_contains($path, "\0")
        ) {
            throw new LocalizedException(__('Invalid local product media path.'));
        }
        $root = $this->mediaRoot();
        $absolute = $root . '/' . $path;
        $resolved = $this->driver->getRealPath($absolute);
        $resolvedRoot = $this->driver->getRealPath($root);
        if ($resolved !== false && ($resolvedRoot === false
            || !str_starts_with($resolved, $resolvedRoot . '/' . self::ROOT))
        ) {
            throw new LocalizedException(__('Local product media path escapes its directory.'));
        }

        return $absolute;
    }

    private function mediaRoot(): string
    {
        return rtrim($this->directories->getPath(DirectoryList::MEDIA), '/');
    }
}
