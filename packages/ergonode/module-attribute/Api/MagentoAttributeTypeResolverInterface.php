<?php

declare(strict_types=1);

namespace Ergonode\Attribute\Api;

interface MagentoAttributeTypeResolverInterface
{
    /**
     * @param string $frontendInput
     * @param string $backendType
     * @param string $sourceModel
     * @return string
     */
    public function fromStorage(string $frontendInput, string $backendType, string $sourceModel): string;

    /**
     * @param string $type
     * @return string|null
     */
    public function toErgonodeType(string $type): ?string;
}
