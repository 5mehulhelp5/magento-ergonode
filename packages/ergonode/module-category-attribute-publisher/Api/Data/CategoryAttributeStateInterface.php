<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributePublisher\Api\Data;

use Ergonode\CategoryPublisher\Api\Data\CategorySynchronizationContributionInterface;

interface CategoryAttributeStateInterface extends CategorySynchronizationContributionInterface
{
    /**
     * @return string[]
     */
    public function getAllowedAttributeCodes(): array;

    /**
     * @return CategoryAttributeValueInterface[]
     */
    public function getValues(): array;
}
