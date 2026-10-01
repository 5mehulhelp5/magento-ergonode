<?php

declare(strict_types=1);

namespace Ergonode\AttributeConsumer\Api;

interface ErgonodeRelationAttributeProviderInterface
{
    /**
     * @return list<array{label: string, code: string, scope: string}>
     */
    public function getRelationAttributes(): array;
}
