<?php

declare(strict_types=1);

namespace Ergonode\ProductAttributeAdminUi\Api;

interface VerifiedErgonodeMetadataRecorderInterface
{
    /**
     * @param array<string, mixed> $attribute
     * @return void
     */
    public function recordAttribute(array $attribute): void;

    /**
     * @param string $attributeCode
     * @param array<int, array<string, mixed>> $options
     * @return void
     */
    public function recordOptions(string $attributeCode, array $options): void;
}
