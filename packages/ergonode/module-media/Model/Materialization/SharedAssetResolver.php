<?php

declare(strict_types=1);

namespace Ergonode\Media\Model\Materialization;

use Ergonode\Media\Api\SharedAssetResolverInterface;
use Ergonode\Media\Model\ResourceModel\MediaRepository;

class SharedAssetResolver implements SharedAssetResolverInterface
{
    public function __construct(
        private readonly MediaRepository $repository,
        private readonly AssetMaterializer $materializer
    ) {
    }

    public function resolve(string $sourcePath, string $targetDirectory): string
    {
        return $this->materializer->shared($this->repository->ensureAsset($sourcePath), $targetDirectory);
    }
}
