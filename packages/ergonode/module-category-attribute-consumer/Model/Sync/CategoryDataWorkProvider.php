<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Sync;

use Ergonode\CategoryAttributeConsumer\Api\CategoryAttributeMappingProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryDataWorkProviderInterface;
use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;

class CategoryDataWorkProvider implements CategoryDataWorkProviderInterface
{
    public function __construct(
        private readonly CategoryAttributeMappingProviderInterface $mappingProvider,
        private readonly CategoryNameTargetProviderInterface $nameTarget
    ) {
    }

    public function hasWork(): bool
    {
        return $this->nameTarget->getAttributeCode() !== null
            || $this->mappingProvider->getValueMappings() !== [];
    }
}
