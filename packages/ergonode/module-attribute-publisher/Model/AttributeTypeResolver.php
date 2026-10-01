<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Model;

use Ergonode\AttributePublisher\Api\AttributeTypeResolverInterface;
use Ergonode\Attribute\Api\MagentoAttributeTypeResolverInterface;

class AttributeTypeResolver implements AttributeTypeResolverInterface
{
    public function __construct(private readonly MagentoAttributeTypeResolverInterface $typeResolver)
    {
    }

    public function resolve(string $sourceType): ?string
    {
        return $this->typeResolver->toErgonodeType($sourceType);
    }
}
