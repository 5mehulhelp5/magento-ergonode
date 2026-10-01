<?php

declare(strict_types=1);

namespace Ergonode\CategoryConsumer\Model\Config;

use Ergonode\CategoryConsumer\Api\CategoryNameTargetProviderInterface;

class CategoryNameTargetProvider implements CategoryNameTargetProviderInterface
{
    public function __construct(private readonly CategoryConfigProvider $configProvider)
    {
    }

    public function getAttributeCode(): ?string
    {
        return $this->configProvider->getNameMode() === 'source' ? 'name' : null;
    }
}
