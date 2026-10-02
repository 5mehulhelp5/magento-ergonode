<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Index;

use Ergonode\Media\Model\Port\LocalFileIndexInterface;
use Magento\Framework\Exception\LocalizedException;

class IndexedFileResolver
{
    public function __construct(private readonly LocalFileIndexInterface $index, private readonly LocalFiles $files)
    {
    }

    public function find(string $contentHash): ?string
    {
        foreach ($this->index->find($contentHash) as $candidate) {
            $path = $candidate['path'];
            $stat = $this->files->stat($path);
            if ($stat === null) {
                $this->index->remove($path);
                continue;
            }
            // Verify a new association; known active asset associations bypass this lookup.
            $actual = $this->files->hash($path);
            $this->index->save($path, $actual, $stat['size'], $stat['modified_at']);
            if (hash_equals($contentHash, $actual)) {
                return $path;
            }
        }

        return null;
    }

    public function remember(string $path, string $contentHash): void
    {
        $stat = $this->files->stat($path);
        if ($stat === null || !hash_equals($contentHash, $this->files->hash($path))) {
            throw new LocalizedException(__('The local media file does not match the expected content.'));
        }
        $this->index->save($path, $contentHash, $stat['size'], $stat['modified_at']);
    }
}
