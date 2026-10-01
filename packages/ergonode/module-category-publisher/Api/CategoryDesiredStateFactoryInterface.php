<?php

declare(strict_types=1);

namespace Ergonode\CategoryPublisher\Api;

use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationContributionInterface;
use Ergonode\CategoryPublisher\Api\Data\CategoryStateInterface;

interface CategoryDesiredStateFactoryInterface
{
    /**
     * @param string $code
     * @param array<string, string> $names
     * @param CategorySynchronizationContributionInterface[] $contributions
     * @param bool $deleted
     * @return CategoryStateInterface
     */
    public function createCategory(
        string $code,
        array $names,
        array $contributions = [],
        bool $deleted = false
    ): CategoryStateInterface;
}
