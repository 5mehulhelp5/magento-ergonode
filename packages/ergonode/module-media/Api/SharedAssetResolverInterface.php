<?php

declare(strict_types=1);

namespace Ergonode\Media\Api;

interface SharedAssetResolverInterface
{
    /**
     * @param string $sourcePath
     * @param string $targetDirectory
     * @return string
     */
    public function resolve(string $sourcePath, string $targetDirectory): string;
}
