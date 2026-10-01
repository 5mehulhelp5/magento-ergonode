<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Api;

use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeStateInterface;
use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeValueInterface;

interface CategoryAttributeStateFactoryInterface
{
    /**
     * @param string[] $allowedAttributeCodes
     * @param CategoryAttributeValueInterface[] $values
     * @return CategoryAttributeStateInterface
     */
    public function create(array $allowedAttributeCodes = [], array $values = []): CategoryAttributeStateInterface;
}
