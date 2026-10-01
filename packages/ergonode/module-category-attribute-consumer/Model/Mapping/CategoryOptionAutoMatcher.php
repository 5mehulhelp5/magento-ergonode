<?php

declare(strict_types=1);

namespace Ergonode\CategoryAttributeConsumer\Model\Mapping;

use Ergonode\CategoryAttribute\Api\OptionAutoMatcherInterface;
use Ergonode\CategoryAttributeConsumer\Api\CategoryOptionAutoMatcherInterface;

class CategoryOptionAutoMatcher implements CategoryOptionAutoMatcherInterface
{
    public function __construct(private readonly OptionAutoMatcherInterface $optionAutoMatcher)
    {
    }

    public function suggest(int $attributeMappingId, array $ergonodeOptions, array $magentoOptions): array
    {
        return $this->optionAutoMatcher->suggest($attributeMappingId, $ergonodeOptions, $magentoOptions);
    }
}
