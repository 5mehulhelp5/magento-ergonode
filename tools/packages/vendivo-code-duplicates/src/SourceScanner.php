<?php

declare(strict_types=1);

namespace Vendivo\CodeDuplicates;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class SourceScanner
{
    public function __construct(private string $projectRoot)
    {
    }

    /**
     * @param list<string> $paths
     * @return array<string, string> display path => absolute path
     */
    public function scan(array $paths, bool $includeTests = false): array
    {
        $files = [];
        foreach ($paths as $path) {
            $absolutePath = realpath($path);
            if ($absolutePath === false) {
                $absolutePath = realpath($this->projectRoot . '/' . $path);
            }
            if ($absolutePath === false) {
                throw new InvalidArgumentException("Path does not exist: {$path}");
            }
            if (is_file($absolutePath)) {
                $this->addFile($files, $absolutePath, $includeTests);
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $this->addFile($files, $file->getPathname(), $includeTests);
                }
            }
        }

        ksort($files, SORT_STRING);
        if ($files === []) {
            throw new InvalidArgumentException('No PHP files found in the selected scope.');
        }

        return $files;
    }

    /** @param array<string, string> $files */
    private function addFile(array &$files, string $path, bool $includeTests): void
    {
        $normalized = str_replace('\\', '/', $path);
        if (!str_ends_with(strtolower($normalized), '.php')) {
            return;
        }

        $root = rtrim(str_replace('\\', '/', $this->projectRoot), '/');
        $displayPath = str_starts_with($normalized, $root . '/')
            ? substr($normalized, strlen($root) + 1)
            : $normalized;
        if (preg_match('~(?:^|/)(?:vendor|generated|var|node_modules|\.git)(?:/|$)~', $displayPath) === 1) {
            return;
        }
        if (!$includeTests && preg_match('~(?:^|/)(?:Test|Tests)(?:/|$)~', $displayPath) === 1) {
            return;
        }

        $files[$displayPath] = $path;
    }
}
