<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Model\Data;

use Ergonode\CategoryAttributePublisher\Api\CategoryAttributeStateFactoryInterface;
use Ergonode\CategoryAttributePublisher\Api\Data\CategoryAttributeStateInterface;

class CategoryAttributeStateFactory implements CategoryAttributeStateFactoryInterface
{
    public function create(array $allowedAttributeCodes = [], array $values = []): CategoryAttributeStateInterface
    {
        return new CategoryAttributeStateDto($allowedAttributeCodes, $values);
    }
}
