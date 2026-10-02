<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Materialization;

use Ergonode\Media\Api\SharedPathStrategyInterface;

class DefaultSharedPathStrategy implements SharedPathStrategyInterface
{
    public function resolve(string $sourcePath, string $contentHash, string $extension, string $directory): string
    {
        unset($sourcePath);
        $hash = bin2hex($contentHash);

        return sprintf(
            '%s/%s/%s/%s.%s',
            trim($directory, '/'),
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash,
            $extension
        );
    }
}
