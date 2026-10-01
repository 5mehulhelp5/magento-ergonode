<?php

declare(strict_types=1);

namespace Ergonode\AttributePublisher\Api;

interface AttributeTypeResolverInterface
{
    /**
     * @param string $sourceType
     * @return string|null
     */
    public function resolve(string $sourceType): ?string;
}
