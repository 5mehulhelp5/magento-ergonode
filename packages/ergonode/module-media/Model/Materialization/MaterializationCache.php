<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Materialization;

use Ergonode\Media\Model\Data\Asset;

/** Reuse resolved paths only within one claimed batch of product media work. */
class MaterializationCache
{
    private bool $active = false;

    /** @var array<string, string> */
    private array $paths = [];

    /** @var array<string, string> Only files whose actual content was verified in this batch. */
    private array $verifiedContent = [];

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        if ($this->active) {
            return $operation();
        }
        $this->active = true;
        try {
            return $operation();
        } finally {
            $this->paths = [];
            $this->verifiedContent = [];
            $this->active = false;
        }
    }

    public function get(Asset $asset, string $scope): ?string
    {
        if (!$this->active || $asset->status !== 'active' || $asset->contentHash === null) {
            return null;
        }
        return $this->paths[$this->key($asset, $scope)] ?? null;
    }

    public function getShared(string $contentHash): ?string
    {
        return $this->active ? ($this->verifiedContent[bin2hex($contentHash)] ?? null) : null;
    }

    public function remember(Asset $asset, string $scope, string $path, bool $contentVerified = false): string
    {
        if ($this->active && $asset->status === 'active' && $asset->contentHash !== null) {
            $this->paths[$this->key($asset, $scope)] = $path;
            if ($contentVerified && str_starts_with($scope, 'shared:')) {
                $this->verifiedContent[bin2hex($asset->contentHash)] = $path;
            }
        }
        return $path;
    }

    private function key(Asset $asset, string $scope): string
    {
        return $asset->id . ':' . $asset->revision . ':' . bin2hex((string)$asset->contentHash) . ':' . $scope;
    }
}
