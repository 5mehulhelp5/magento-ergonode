<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

interface SharedPathStrategyInterface
{
    /**
     * Resolve a target for new shared content. The default strategy uses the full SHA-256.
     * Existing materializations and indexed files take precedence over this candidate.
     *
     * @param string $sourcePath
     * @param string $contentHash
     * @param string $extension
     * @param string $directory
     * @return string
     */
    public function resolve(string $sourcePath, string $contentHash, string $extension, string $directory): string;
}
