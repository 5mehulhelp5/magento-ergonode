<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Api;

interface ErgonodeAttributeTypeResolverInterface extends ErgonodeAttributeTypeInterface
{
    /**
     * @param string $typeName
     * @return string|null
     */
    public function fromDefinitionTypeName(string $typeName): ?string;

    /**
     * @param string $typeName
     * @return string|null
     */
    public function fromValueTypeName(string $typeName): ?string;

    /**
     * @param string $type
     * @return string
     */
    public function fromConsumerType(string $type): string;

    /**
     * @param string $type
     * @return string
     */
    public function toConsumerType(string $type): string;
}
