<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Model\Data;

use Ergonode\CategoryPublisher\Api\CategoryDesiredStateFactoryInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;

class CategoryDesiredStateFactory implements CategoryDesiredStateFactoryInterface
{
    public function createCategory(
        string $code,
        array $names,
        array $contributions = [],
        bool $deleted = false
    ): CategoryStateInterface {
        return new CategoryStateDto($code, $names, $contributions, $deleted);
    }
}
