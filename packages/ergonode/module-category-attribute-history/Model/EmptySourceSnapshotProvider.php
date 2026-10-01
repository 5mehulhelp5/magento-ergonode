<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeHistory\Model;

use Ergonode\CategoryAttributeHistory\Api\SourceSnapshotProviderInterface;

class EmptySourceSnapshotProvider implements SourceSnapshotProviderInterface
{
    public function getAttributeMap(): array
    {
        return [];
    }
}
