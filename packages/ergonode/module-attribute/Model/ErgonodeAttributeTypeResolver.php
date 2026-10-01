<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Model;

use Ergonode\Attribute\Api\ErgonodeAttributeTypeResolverInterface;

class ErgonodeAttributeTypeResolver implements ErgonodeAttributeTypeResolverInterface
{
    public function fromDefinitionTypeName(string $typeName): ?string
    {
        return self::DEFINITION_TYPE_NAMES[trim($typeName)] ?? null;
    }

    public function fromValueTypeName(string $typeName): ?string
    {
        return self::VALUE_TYPE_NAMES[trim($typeName)] ?? null;
    }

    public function fromConsumerType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'multiselect' => self::TYPE_MULTI_SELECT,
            'relation' => self::TYPE_PRODUCT_RELATION,
            default => strtolower(trim($type)),
        };
    }

    public function toConsumerType(string $type): string
    {
        return match (strtolower(trim($type))) {
            self::TYPE_MULTI_SELECT => 'multiselect',
            self::TYPE_PRODUCT_RELATION => 'relation',
            default => strtolower(trim($type)),
        };
    }
}
