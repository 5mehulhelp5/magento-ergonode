<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Data;

class Asset
{
    public function __construct(
        public readonly int $id,
        public readonly string $sourcePath,
        public readonly ?string $url,
        public readonly string $name,
        public readonly string $extension,
        public readonly string $mime,
        public readonly ?string $contentHash,
        public readonly ?string $cachePath,
        public readonly int $revision,
        public readonly string $status
    ) {
    }
}
